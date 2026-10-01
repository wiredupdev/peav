<?php

declare(strict_types=1);

/*
 * This file is part of the Peav package.
 *
 * (c) WireUpDev <wireupdev@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WireUpDev\Peav\Query;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WireUpDev\Peav\Caster\BlobTypeCaster;
use WireUpDev\Peav\Caster\BooleanTypeCaster;
use WireUpDev\Peav\Caster\DateTimeTypeCaster;
use WireUpDev\Peav\Caster\DecimalTypeCaster;
use WireUpDev\Peav\Caster\IntegerTypeCaster;
use WireUpDev\Peav\Caster\JsonTypeCaster;
use WireUpDev\Peav\Caster\StringTypeCaster;
use WireUpDev\Peav\Caster\TextTypeCaster;
use WireUpDev\Peav\Caster\TypeCasterInterface;
use WireUpDev\Peav\Exception\EntityTypeNotFoundException;
use WireUpDev\Peav\Flat\FlatConfig;
use WireUpDev\Peav\Flat\FlatStorageRegistry;
use WireUpDev\Peav\Flat\FlatStrategyMode;
use WireUpDev\Peav\Model\EavEntity;
use WireUpDev\Peav\Repository\AttributeRepositoryInterface;
use WireUpDev\Peav\Repository\EavRepositoryInterface;
use WireUpDev\Peav\Schema\TableConfig;
use WireUpDev\Peav\Type\StorageBucket;
use WireUpDev\Peav\Type\TypeRegistry;

/**
 * Fluent query builder for filtering, ordering, and hydrating EAV entities with intelligent flat table / view routing.
 */
class EavQueryBuilder
{
    private readonly LoggerInterface $logger;
    private readonly JoinAliasGenerator $joinAliasGenerator;
    private bool $flatRouting = true;

    /**
     * @var list<AttributeCriteria>
     */
    private array $criteria = [];

    /**
     * @var list<array{attribute: string, direction: string, isEntityProperty: bool}>
     */
    private array $orders = [];

    private ?int $limit = null;
    private ?int $offset = null;

    /**
     * @var array<string, TypeCasterInterface>
     */
    private array $casters = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly string $entityTypeCode,
        private readonly AttributeRepositoryInterface $attributeRepository,
        private readonly TypeRegistry $typeRegistry = new TypeRegistry(),
        private readonly TableConfig $tableConfig = new TableConfig(),
        private readonly FlatConfig $flatConfig = new FlatConfig(),
        private readonly ?FlatStorageRegistry $flatStorageRegistry = null,
        private readonly ?EavRepositoryInterface $eavRepository = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->joinAliasGenerator = new JoinAliasGenerator();

        $this->initializeCasters();
    }

    public function whereAttribute(string $attributeCode, string $operator, mixed $value = null): self
    {
        $this->criteria[] = new AttributeCriteria($attributeCode, $operator, $value, 'AND');

        return $this;
    }

    public function orWhereAttribute(string $attributeCode, string $operator, mixed $value = null): self
    {
        $this->criteria[] = new AttributeCriteria($attributeCode, $operator, $value, 'OR');

        return $this;
    }

    public function where(string $attributeCode, string $operator, mixed $value = null): self
    {
        return $this->whereAttribute($attributeCode, $operator, $value);
    }

    public function orWhere(string $attributeCode, string $operator, mixed $value = null): self
    {
        return $this->orWhereAttribute($attributeCode, $operator, $value);
    }

    public function whereAttributeBetween(string $attributeCode, mixed $from, mixed $to): self
    {
        $this->criteria[] = AttributeCriteria::between($attributeCode, $from, $to, 'AND');

        return $this;
    }

    public function orWhereAttributeBetween(string $attributeCode, mixed $from, mixed $to): self
    {
        $this->criteria[] = AttributeCriteria::between($attributeCode, $from, $to, 'OR');

        return $this;
    }

    /**
     * @param list<mixed> $values
     */
    public function whereAttributeIn(string $attributeCode, array $values): self
    {
        $this->criteria[] = AttributeCriteria::in($attributeCode, $values, 'AND');

        return $this;
    }

    /**
     * @param list<mixed> $values
     */
    public function orWhereAttributeIn(string $attributeCode, array $values): self
    {
        $this->criteria[] = AttributeCriteria::in($attributeCode, $values, 'OR');

        return $this;
    }

    /**
     * @param list<mixed> $values
     */
    public function whereAttributeNotIn(string $attributeCode, array $values): self
    {
        $this->criteria[] = AttributeCriteria::notIn($attributeCode, $values, 'AND');

        return $this;
    }

    /**
     * @param list<mixed> $values
     */
    public function orWhereAttributeNotIn(string $attributeCode, array $values): self
    {
        $this->criteria[] = AttributeCriteria::notIn($attributeCode, $values, 'OR');

        return $this;
    }

    public function whereAttributeNull(string $attributeCode): self
    {
        $this->criteria[] = AttributeCriteria::isNull($attributeCode, 'AND');

        return $this;
    }

    public function orWhereAttributeNull(string $attributeCode): self
    {
        $this->criteria[] = AttributeCriteria::isNull($attributeCode, 'OR');

        return $this;
    }

    public function whereAttributeNotNull(string $attributeCode): self
    {
        $this->criteria[] = AttributeCriteria::isNotNull($attributeCode, 'AND');

        return $this;
    }

    public function orWhereAttributeNotNull(string $attributeCode): self
    {
        $this->criteria[] = AttributeCriteria::isNotNull($attributeCode, 'OR');

        return $this;
    }

    public function whereAttributeLike(string $attributeCode, string $pattern): self
    {
        $this->criteria[] = AttributeCriteria::like($attributeCode, $pattern, 'AND');

        return $this;
    }

    public function orWhereAttributeLike(string $attributeCode, string $pattern): self
    {
        $this->criteria[] = AttributeCriteria::like($attributeCode, $pattern, 'OR');

        return $this;
    }

    public function whereId(int|string $id): self
    {
        $this->criteria[] = AttributeCriteria::forEntityProperty('id', '=', (int) $id, 'AND');

        return $this;
    }

    /**
     * @param list<int|string> $ids
     */
    public function whereIdIn(array $ids): self
    {
        $intIds = array_map('intval', $ids);
        $this->criteria[] = AttributeCriteria::forEntityProperty('id', 'IN', $intIds, 'AND');

        return $this;
    }

    public function whereCreatedAfter(\DateTimeInterface $date): self
    {
        $this->criteria[] = AttributeCriteria::forEntityProperty('created_at', '>=', $date->format('Y-m-d H:i:s'), 'AND');

        return $this;
    }

    public function whereCreatedBefore(\DateTimeInterface $date): self
    {
        $this->criteria[] = AttributeCriteria::forEntityProperty('created_at', '<=', $date->format('Y-m-d H:i:s'), 'AND');

        return $this;
    }

    public function whereUpdatedAfter(\DateTimeInterface $date): self
    {
        $this->criteria[] = AttributeCriteria::forEntityProperty('updated_at', '>=', $date->format('Y-m-d H:i:s'), 'AND');

        return $this;
    }

    public function whereUpdatedBefore(\DateTimeInterface $date): self
    {
        $this->criteria[] = AttributeCriteria::forEntityProperty('updated_at', '<=', $date->format('Y-m-d H:i:s'), 'AND');

        return $this;
    }

    public function orderByAttribute(string $attributeCode, string $direction = 'ASC'): self
    {
        $this->orders[] = [
            'attribute' => $attributeCode,
            'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC',
            'isEntityProperty' => false,
        ];

        return $this;
    }

    public function orderById(string $direction = 'ASC'): self
    {
        $this->orders[] = [
            'attribute' => 'id',
            'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC',
            'isEntityProperty' => true,
        ];

        return $this;
    }

    public function orderByCreatedAt(string $direction = 'ASC'): self
    {
        $this->orders[] = [
            'attribute' => 'created_at',
            'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC',
            'isEntityProperty' => true,
        ];

        return $this;
    }

    public function orderByUpdatedAt(string $direction = 'ASC'): self
    {
        $this->orders[] = [
            'attribute' => 'updated_at',
            'direction' => strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC',
            'isEntityProperty' => true,
        ];

        return $this;
    }

    public function setFirstResult(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    public function offset(int $offset): self
    {
        return $this->setFirstResult($offset);
    }

    public function setMaxResults(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function limit(int $limit, ?int $offset = null): self
    {
        $this->limit = $limit;
        if ($offset !== null) {
            $this->offset = $offset;
        }

        return $this;
    }

    public function paginate(int $page, int $perPage): self
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $this->limit = $perPage;
        $this->offset = ($page - 1) * $perPage;

        return $this;
    }

    public function withFlatRouting(bool $enable = true): self
    {
        $this->flatRouting = $enable;

        return $this;
    }

    public function isFlatRoutingEnabled(): bool
    {
        return $this->flatRouting;
    }

    public function getTypeRegistry(): TypeRegistry
    {
        return $this->typeRegistry;
    }

    public function getFlatStorageRegistry(): ?FlatStorageRegistry
    {
        return $this->flatStorageRegistry;
    }

    public function getAttributeRepository(): AttributeRepositoryInterface
    {
        return $this->attributeRepository;
    }

    public function getTableConfig(): TableConfig
    {
        return $this->tableConfig;
    }

    public function getFlatConfig(): FlatConfig
    {
        return $this->flatConfig;
    }

    public function getEntityTypeCode(): string
    {
        return $this->entityTypeCode;
    }

    /**
     * Determines the optimal query execution routing strategy: 'flat_table', 'flat_view', or 'normalized_eav'.
     */
    public function getRoutingDecision(): string
    {
        if (!$this->flatRouting) {
            return 'normalized_eav';
        }

        $entityType = $this->attributeRepository->getEntityType($this->entityTypeCode);
        if ($entityType === null) {
            return 'normalized_eav';
        }

        $mode = $this->flatConfig->getModeFor($this->entityTypeCode);
        if ($mode === FlatStrategyMode::None || $mode === FlatStrategyMode::Custom) {
            return 'normalized_eav';
        }

        // Verify that all criteria target known presets
        foreach ($this->criteria as $criterion) {
            if ($criterion->isEntityProperty()) {
                continue;
            }
            $code = $criterion->getAttributeCode();
            if (!$entityType->hasPreset($code)) {
                return 'normalized_eav';
            }
        }

        // Verify that all order attributes target known presets
        foreach ($this->orders as $order) {
            if ($order['isEntityProperty']) {
                continue;
            }
            if (!$entityType->hasPreset($order['attribute'])) {
                return 'normalized_eav';
            }
        }

        if ($mode === FlatStrategyMode::Physical) {
            return 'flat_table';
        }

        return 'flat_view';
    }

    /**
     * Executes the query and returns fully hydrated EavEntity objects.
     *
     * @return list<EavEntity>
     */
    public function getEntities(): array
    {
        $startTime = microtime(true);
        $decision = $this->getRoutingDecision();

        $rows = $this->fetchRawRows($decision);
        if (empty($rows)) {
            $this->logExecution($decision, 0, microtime(true) - $startTime);

            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['entity_id'] ?? $row['id']);
            $ids[] = $id;
        }

        if ($this->eavRepository !== null) {
            $entitiesMap = $this->eavRepository->findMany($this->entityTypeCode, $ids);
            $orderedEntities = [];
            foreach ($ids as $id) {
                if (isset($entitiesMap[$id])) {
                    $orderedEntities[] = $entitiesMap[$id];
                }
            }
            $this->logExecution($decision, count($orderedEntities), microtime(true) - $startTime);

            return $orderedEntities;
        }

        // Fallback hydration if no EavRepository is provided
        $entityType = $this->attributeRepository->getEntityType($this->entityTypeCode);
        $customClass = $entityType?->getCustomClass();
        $entities = [];

        foreach ($rows as $row) {
            $id = (int) ($row['entity_id'] ?? $row['id']);
            if ($customClass !== null && is_subclass_of($customClass, EavEntity::class)) {
                $entity = new $customClass($this->entityTypeCode, $id);
            } else {
                $entity = new EavEntity($this->entityTypeCode, $id);
            }

            if (!empty($row['created_at'])) {
                $entity->setCreatedAt(new \DateTimeImmutable((string) $row['created_at']));
            }
            if (!empty($row['updated_at'])) {
                $entity->setUpdatedAt(new \DateTimeImmutable((string) $row['updated_at']));
            }

            $entities[] = $entity;
        }

        $this->logExecution($decision, count($entities), microtime(true) - $startTime);

        return $entities;
    }

    /**
     * Alias for getEntities().
     *
     * @return list<EavEntity>
     */
    public function execute(): array
    {
        return $this->getEntities();
    }

    /**
     * Returns the first matching entity or null.
     */
    public function first(): ?EavEntity
    {
        $originalLimit = $this->limit;
        $this->limit = 1;

        $results = $this->getEntities();
        $this->limit = $originalLimit;

        return $results[0] ?? null;
    }

    /**
     * Returns the total count of matching entities.
     */
    public function count(): int
    {
        $decision = $this->getRoutingDecision();
        $dbalQb = $this->connection->createQueryBuilder();

        if ($decision === 'flat_table' || $decision === 'flat_view') {
            $tableName = $decision === 'flat_table'
                ? $this->tableConfig->getFlatTableName($this->entityTypeCode)
                : $this->tableConfig->getFlatViewName($this->entityTypeCode);

            $dbalQb->select('COUNT(*)')->from($tableName, 'e');
            $this->applyFlatCriteria($dbalQb);
        } else {
            $entitiesTable = $this->tableConfig->getEntitiesTable();
            $entityTypesTable = $this->tableConfig->getEntityTypesTable();

            $dbalQb->select('COUNT(DISTINCT e.id)')
                ->from($entitiesTable, 'e')
                ->innerJoin('e', $entityTypesTable, 'et', 'et.id = e.entity_type_id AND et.code = :peav_type_code')
                ->setParameter('peav_type_code', $this->entityTypeCode);

            $this->applyNormalizedCriteria($dbalQb);
        }

        return (int) $dbalQb->executeQuery()->fetchOne();
    }

    /**
     * Returns a list of matching entity primary keys.
     *
     * @return list<int>
     */
    public function getIds(): array
    {
        $rows = $this->fetchRawRows($this->getRoutingDecision());
        $ids = [];

        foreach ($rows as $row) {
            $ids[] = (int) ($row['entity_id'] ?? $row['id']);
        }

        return $ids;
    }

    /**
     * Returns raw database result rows.
     *
     * @return list<array<string, mixed>>
     */
    public function getRawResults(): array
    {
        return $this->fetchRawRows($this->getRoutingDecision());
    }

    /**
     * Compiles and returns the generated SQL statement.
     */
    public function getSql(): string
    {
        $decision = $this->getRoutingDecision();
        $dbalQb = $this->buildDbalQueryBuilder($decision);

        return $dbalQb->getSQL();
    }

    /**
     * Returns the query parameter bindings.
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        $decision = $this->getRoutingDecision();
        $dbalQb = $this->buildDbalQueryBuilder($decision);

        /** @var array<string, mixed> $params */
        $params = $dbalQb->getParameters();

        return $params;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRawRows(string $decision): array
    {
        $dbalQb = $this->buildDbalQueryBuilder($decision);

        /** @var list<array<string, mixed>> $rows */
        $rows = $dbalQb->executeQuery()->fetchAllAssociative();

        return $rows;
    }

    private function buildDbalQueryBuilder(string $decision): QueryBuilder
    {
        $this->joinAliasGenerator->reset();
        $dbalQb = $this->connection->createQueryBuilder();

        if ($decision === 'flat_table' || $decision === 'flat_view') {
            $tableName = $decision === 'flat_table'
                ? $this->tableConfig->getFlatTableName($this->entityTypeCode)
                : $this->tableConfig->getFlatViewName($this->entityTypeCode);

            $dbalQb->select('e.*')
                ->from($tableName, 'e');

            $this->applyFlatCriteria($dbalQb);
            $this->applyFlatOrders($dbalQb);
        } else {
            $entitiesTable = $this->tableConfig->getEntitiesTable();
            $entityTypesTable = $this->tableConfig->getEntityTypesTable();

            $dbalQb->select('DISTINCT e.id, e.created_at, e.updated_at')
                ->from($entitiesTable, 'e')
                ->innerJoin('e', $entityTypesTable, 'et', 'et.id = e.entity_type_id AND et.code = :peav_type_code')
                ->setParameter('peav_type_code', $this->entityTypeCode);

            $this->applyNormalizedCriteria($dbalQb);
            $this->applyNormalizedOrders($dbalQb);
        }

        if ($this->limit !== null) {
            $dbalQb->setMaxResults($this->limit);
        }
        if ($this->offset !== null) {
            $dbalQb->setFirstResult($this->offset);
        }

        return $dbalQb;
    }

    private function applyFlatCriteria(QueryBuilder $dbalQb): void
    {
        $paramIdx = 0;

        foreach ($this->criteria as $criterion) {
            $paramName = sprintf('p_%d', ++$paramIdx);
            $attrCode = $criterion->getAttributeCode();
            $column = $criterion->isEntityProperty()
                ? ($attrCode === 'id' ? 'e.entity_id' : 'e.' . $attrCode)
                : 'e.' . $attrCode;

            $op = $criterion->getOperator();
            $val = $criterion->getValue();

            $expr = $this->buildConditionExpression($dbalQb, $column, $op, $val, $paramName);

            if ($criterion->getConjunction() === 'OR') {
                $dbalQb->orWhere($expr);
            } else {
                $dbalQb->andWhere($expr);
            }
        }
    }

    private function applyFlatOrders(QueryBuilder $dbalQb): void
    {
        foreach ($this->orders as $order) {
            $attr = $order['attribute'];
            $column = $order['isEntityProperty']
                ? ($attr === 'id' ? 'e.entity_id' : 'e.' . $attr)
                : 'e.' . $attr;

            $dbalQb->addOrderBy($column, $order['direction']);
        }
    }

    private function applyNormalizedCriteria(QueryBuilder $dbalQb): void
    {
        $attributesTable = $this->tableConfig->getAttributesTable();
        $paramIdx = 0;
        $joinedAttributes = [];

        foreach ($this->criteria as $criterion) {
            $paramName = sprintf('np_%d', ++$paramIdx);
            $attrCode = $criterion->getAttributeCode();

            if ($criterion->isEntityProperty()) {
                $column = 'e.' . $attrCode;
            } else {
                if (!isset($joinedAttributes[$attrCode])) {
                    $attr = $this->attributeRepository->getAttribute($attrCode);
                    $bucket = $attr?->getType()->getStorageBucket() ?? StorageBucket::String;
                    $valTable = $this->tableConfig->getValueTableName($bucket);

                    $attrAlias = $this->joinAliasGenerator->getAttributeAlias($attrCode);
                    $valAlias = $this->joinAliasGenerator->getValueAlias($attrCode);

                    $attrParam = sprintf('code_%s', $attrAlias);
                    $dbalQb->leftJoin('e', $attributesTable, $attrAlias, sprintf('%s.code = :%s', $attrAlias, $attrParam))
                        ->leftJoin('e', $valTable, $valAlias, sprintf('%s.entity_id = e.id AND %s.attribute_id = %s.id', $valAlias, $valAlias, $attrAlias))
                        ->setParameter($attrParam, $attrCode);

                    $joinedAttributes[$attrCode] = $valAlias;
                }

                $column = sprintf('%s.value', $joinedAttributes[$attrCode]);
            }

            $op = $criterion->getOperator();
            $val = $this->castValueForCondition($attrCode, $criterion->getValue());

            $expr = $this->buildConditionExpression($dbalQb, $column, $op, $val, $paramName);

            if ($criterion->getConjunction() === 'OR') {
                $dbalQb->orWhere($expr);
            } else {
                $dbalQb->andWhere($expr);
            }
        }
    }

    private function applyNormalizedOrders(QueryBuilder $dbalQb): void
    {
        $attributesTable = $this->tableConfig->getAttributesTable();

        foreach ($this->orders as $order) {
            $attrCode = $order['attribute'];

            if ($order['isEntityProperty']) {
                $column = 'e.' . $attrCode;
            } else {
                $attr = $this->attributeRepository->getAttribute($attrCode);
                $bucket = $attr?->getType()->getStorageBucket() ?? StorageBucket::String;
                $valTable = $this->tableConfig->getValueTableName($bucket);

                $attrAlias = $this->joinAliasGenerator->getAttributeAlias($attrCode);
                $valAlias = $this->joinAliasGenerator->getValueAlias($attrCode);

                $attrParam = sprintf('code_%s', $attrAlias);
                $dbalQb->leftJoin('e', $attributesTable, $attrAlias, sprintf('%s.code = :%s', $attrAlias, $attrParam))
                    ->leftJoin('e', $valTable, $valAlias, sprintf('%s.entity_id = e.id AND %s.attribute_id = %s.id', $valAlias, $valAlias, $attrAlias))
                    ->setParameter($attrParam, $attrCode);

                $column = sprintf('%s.value', $valAlias);
            }

            $dbalQb->addOrderBy($column, $order['direction']);
        }
    }

    private function buildConditionExpression(QueryBuilder $dbalQb, string $column, string $op, mixed $val, string $paramName): string
    {
        return match ($op) {
            'IS NULL' => sprintf('%s IS NULL', $column),
            'IS NOT NULL' => sprintf('%s IS NOT NULL', $column),
            'IN' => $this->bindArrayCondition($dbalQb, $column, 'IN', (array) $val, $paramName),
            'NOT IN' => $this->bindArrayCondition($dbalQb, $column, 'NOT IN', (array) $val, $paramName),
            'BETWEEN' => $this->bindBetweenCondition($dbalQb, $column, (array) $val, $paramName),
            default => $this->bindScalarCondition($dbalQb, $column, $op, $val, $paramName),
        };
    }

    /**
     * @param list<mixed> $values
     */
    private function bindArrayCondition(QueryBuilder $dbalQb, string $column, string $op, array $values, string $paramName): string
    {
        $dbalQb->setParameter($paramName, $values, ArrayParameterType::STRING);

        return sprintf('%s %s (:%s)', $column, $op, $paramName);
    }

    /**
     * @param array<mixed> $values
     */
    private function bindBetweenCondition(QueryBuilder $dbalQb, string $column, array $values, string $paramName): string
    {
        $paramFrom = $paramName . '_from';
        $paramTo = $paramName . '_to';

        $dbalQb->setParameter($paramFrom, $values[0] ?? null);
        $dbalQb->setParameter($paramTo, $values[1] ?? null);

        return sprintf('%s BETWEEN :%s AND :%s', $column, $paramFrom, $paramTo);
    }

    private function bindScalarCondition(QueryBuilder $dbalQb, string $column, string $op, mixed $val, string $paramName): string
    {
        // Handle boolean values for SQLite / standard DBAL
        if (is_bool($val)) {
            $val = $val ? 1 : 0;
        }

        $dbalQb->setParameter($paramName, $val);

        return sprintf('%s %s :%s', $column, $op, $paramName);
    }

    private function castValueForCondition(string $attributeCode, mixed $val): mixed
    {
        if ($val === null || is_array($val)) {
            return $val;
        }

        $attr = $this->attributeRepository->getAttribute($attributeCode);
        if ($attr === null) {
            return $val;
        }

        $type = $attr->getType();
        $bucket = $type->getStorageBucket();
        $caster = $this->casters[$bucket->value] ?? null;

        if ($caster !== null) {
            try {
                return $caster->convertToDatabaseValue($val, $type, $this->connection->getDatabasePlatform());
            } catch (\Throwable) {
                return $val;
            }
        }

        return $val;
    }

    private function initializeCasters(): void
    {
        $this->casters[StorageBucket::String->value] = new StringTypeCaster();
        $this->casters[StorageBucket::Integer->value] = new IntegerTypeCaster();
        $this->casters[StorageBucket::Decimal->value] = new DecimalTypeCaster();
        $this->casters[StorageBucket::DateTime->value] = new DateTimeTypeCaster();
        $this->casters[StorageBucket::Boolean->value] = new BooleanTypeCaster();
        $this->casters[StorageBucket::Text->value] = new TextTypeCaster();
        $this->casters[StorageBucket::Json->value] = new JsonTypeCaster();
        $this->casters[StorageBucket::Blob->value] = new BlobTypeCaster();
    }

    private function logExecution(string $decision, int $resultCount, float $duration): void
    {
        $this->logger->info(sprintf(
            'Executed EAV query for entity type "%s" via [%s] strategy in %0.4fs. Results: %d',
            $this->entityTypeCode,
            $decision,
            $duration,
            $resultCount,
        ));
    }
}
