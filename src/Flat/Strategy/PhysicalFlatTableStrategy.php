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

namespace WireUpDev\Peav\Flat\Strategy;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WireUpDev\Peav\Flat\FlatConfig;
use WireUpDev\Peav\Flat\FlatStorageStrategyInterface;
use WireUpDev\Peav\Flat\FlatTableBuilder;
use WireUpDev\Peav\Model\EavEntity;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Schema\TableConfig;
use WireUpDev\Peav\Type\StorageBucket;

/**
 * Materializes and synchronizes physical flat SQL tables for high-throughput single-table querying.
 */
class PhysicalFlatTableStrategy implements FlatStorageStrategyInterface
{
    private readonly LoggerInterface $logger;

    /**
     * @var array<string, bool>
     */
    private array $syncedCache = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly FlatTableBuilder $flatTableBuilder,
        private readonly TableConfig $tableConfig = new TableConfig(),
        private readonly FlatConfig $flatConfig = new FlatConfig(),
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function getStrategyName(): string
    {
        return 'physical';
    }

    public function syncSchema(EntityTypeDefinition $entityType): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $targetTable = $this->flatTableBuilder->buildTable($entityType);
        $tableName = $targetTable->getName();

        if (!$schemaManager->tableExists($tableName)) {
            $this->logger->info(sprintf('Creating physical flat table "%s".', $tableName));
            $schemaManager->createTable($targetTable);
            $this->syncedCache[$entityType->getCode()] = true;

            return;
        }

        $currentTable = $schemaManager->introspectTable($tableName);
        $comparator = $schemaManager->createComparator();
        $diff = $comparator->compareTables($currentTable, $targetTable);

        if (!$diff->isEmpty()) {
            $this->logger->info(sprintf('Altering physical flat table "%s" schema diff.', $tableName));
            $schemaManager->alterTable($diff);
        }

        $this->syncedCache[$entityType->getCode()] = true;
    }

    public function dropSchema(EntityTypeDefinition $entityType): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $tableName = $this->tableConfig->getFlatTableName($entityType->getCode());
        unset($this->syncedCache[$entityType->getCode()]);

        if ($schemaManager->tableExists($tableName)) {
            $this->logger->info(sprintf('Dropping physical flat table "%s".', $tableName));
            $schemaManager->dropTable($tableName);
        }
    }

    public function isSynchronized(EntityTypeDefinition $entityType): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        $tableName = $this->tableConfig->getFlatTableName($entityType->getCode());

        return $schemaManager->tableExists($tableName);
    }

    public function indexEntity(EavEntity $entity, EntityTypeDefinition $entityType): void
    {
        if ($entity->getId() === null) {
            return;
        }

        $typeCode = $entityType->getCode();
        if (!isset($this->syncedCache[$typeCode])) {
            $this->syncSchema($entityType);
            $this->syncedCache[$typeCode] = true;
        }

        $tableName = $this->tableConfig->getFlatTableName($typeCode);
        $data = $this->extractEntityRow($entity, $entityType);
        $types = $this->extractEntityColumnTypes($entityType);

        // Delete existing row then insert
        $this->connection->delete($tableName, ['entity_id' => $entity->getId()]);
        $this->connection->insert($tableName, $data, $types);
    }

    public function removeEntity(int|string $entityId, EntityTypeDefinition $entityType): void
    {
        $typeCode = $entityType->getCode();
        if (!isset($this->syncedCache[$typeCode])) {
            if (!$this->isSynchronized($entityType)) {
                return;
            }
            $this->syncedCache[$typeCode] = true;
        }

        $tableName = $this->tableConfig->getFlatTableName($typeCode);
        $this->connection->delete($tableName, ['entity_id' => $entityId]);
    }

    public function batchIndex(array $entities, EntityTypeDefinition $entityType): void
    {
        if (empty($entities)) {
            return;
        }

        $this->connection->transactional(function () use ($entities, $entityType): void {
            foreach ($entities as $entity) {
                $this->indexEntity($entity, $entityType);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function extractEntityRow(EavEntity $entity, EntityTypeDefinition $entityType): array
    {
        $now = new \DateTimeImmutable();
        $createdAt = $entity->getCreatedAt() ?? $now;
        $updatedAt = $entity->getUpdatedAt();

        $row = [
            'entity_id' => $entity->getId(),
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];

        foreach ($entityType->getPresets() as $preset) {
            $code = $preset->getAttributeCode();
            $val = $entity->get($code, $preset->getDefaultValue());
            $type = $preset->getAttribute()->getType();
            $bucket = $type->getStorageBucket();
            $dbalType = $type->getDbalTypeName();

            if ($val !== null && $bucket === StorageBucket::Json && !is_string($val)) {
                $val = json_encode($val, JSON_THROW_ON_ERROR);
            }

            if ($val !== null && in_array($dbalType, [Types::DATETIME_IMMUTABLE, Types::DATETIME_MUTABLE, Types::DATE_IMMUTABLE, Types::DATE_MUTABLE], true)) {
                if (is_string($val)) {
                    $val = new \DateTimeImmutable($val);
                }
            }

            $row[$code] = $val;
        }

        return $row;
    }

    /**
     * @return array<string, string>
     */
    private function extractEntityColumnTypes(EntityTypeDefinition $entityType): array
    {
        $types = [
            'entity_id' => Types::BIGINT,
            'created_at' => Types::DATETIME_IMMUTABLE,
            'updated_at' => Types::DATETIME_IMMUTABLE,
        ];

        foreach ($entityType->getPresets() as $preset) {
            $types[$preset->getAttributeCode()] = $preset->getAttribute()->getType()->getDbalTypeName();
        }

        return $types;
    }
    public function getTableConfig(): TableConfig
    {
        return $this->tableConfig;
    }

    public function getFlatConfig(): FlatConfig
    {
        return $this->flatConfig;
    }
}
