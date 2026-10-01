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

namespace WireUpDev\Peav\Flat;

use Doctrine\DBAL\Connection;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WireUpDev\Peav\Event\FlatTableReindexedEvent;
use WireUpDev\Peav\Event\FlatTableReindexingEvent;
use WireUpDev\Peav\Factory\NullAdapters\NullEventDispatcher;
use WireUpDev\Peav\Model\EavEntity;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Repository\AttributeRepositoryInterface;
use WireUpDev\Peav\Schema\TableConfig;
use WireUpDev\Peav\Type\StorageBucket;

/**
 * Dispatches real-time entity indexing and coordinates chunked batch re-indexing across active flat strategies.
 */
class FlatIndexer implements FlatIndexerInterface
{
    private readonly EventDispatcherInterface $eventDispatcher;
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly Connection $connection,
        private readonly FlatStorageRegistry $flatStorageRegistry,
        private readonly AttributeRepositoryInterface $attributeRepository,
        private readonly TableConfig $tableConfig = new TableConfig(),
        private readonly FlatConfig $flatConfig = new FlatConfig(),
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->eventDispatcher = $eventDispatcher ?? new NullEventDispatcher();
        $this->logger = $logger ?? new NullLogger();
    }

    public function indexEntity(EavEntity $entity, EntityTypeDefinition $entityType): void
    {
        if (!$this->flatConfig->isRealtimeIndexing()) {
            return;
        }

        $strategies = $this->flatStorageRegistry->getStrategiesFor($entityType->getCode());
        foreach ($strategies as $strategy) {
            $strategy->indexEntity($entity, $entityType);
        }
    }

    public function removeEntity(int|string $entityId, EntityTypeDefinition $entityType): void
    {
        if (!$this->flatConfig->isRealtimeIndexing()) {
            return;
        }

        $strategies = $this->flatStorageRegistry->getStrategiesFor($entityType->getCode());
        foreach ($strategies as $strategy) {
            $strategy->removeEntity($entityId, $entityType);
        }
    }

    public function reindexAll(?string $entityTypeCode = null, int $batchSize = 500): int
    {
        $this->eventDispatcher->dispatch(new FlatTableReindexingEvent($entityTypeCode, $batchSize));
        $this->logger->info(sprintf('Starting batch re-indexing for flat projections (entity type: %s, batch: %d).', $entityTypeCode ?? 'ALL', $batchSize));

        $entityTypes = [];
        if ($entityTypeCode !== null) {
            $def = $this->attributeRepository->getEntityType($entityTypeCode);
            if ($def !== null) {
                $entityTypes[] = $def;
            }
        } else {
            $entityTypes = array_values($this->attributeRepository->getAllEntityTypes());
        }

        $totalIndexed = 0;

        foreach ($entityTypes as $entityType) {

            $strategies = $this->flatStorageRegistry->getStrategiesFor($entityType->getCode());
            if (empty($strategies)) {
                continue;
            }

            // Sync schema first
            foreach ($strategies as $strategy) {
                $strategy->syncSchema($entityType);
            }

            $entitiesTable = $this->tableConfig->getEntitiesTable();
            $entityTypesTable = $this->tableConfig->getEntityTypesTable();

            $typeId = (int) $this->connection->fetchOne(
                sprintf('SELECT id FROM %s WHERE code = ?', $entityTypesTable),
                [$entityType->getCode()],
            );

            if ($typeId === 0) {
                continue;
            }

            $offset = 0;
            while (true) {
                $rows = $this->connection->fetchAllAssociative(
                    sprintf('SELECT id, created_at, updated_at FROM %s WHERE entity_type_id = ? ORDER BY id ASC LIMIT %d OFFSET %d', $entitiesTable, $batchSize, $offset),
                    [$typeId],
                );

                if (empty($rows)) {
                    break;
                }

                $entityIds = array_map(static fn (array $r): int => (int) $r['id'], $rows);
                $entityMap = [];

                foreach ($rows as $row) {
                    $id = (int) $row['id'];
                    $entity = new EavEntity($entityType->getCode(), $id);
                    $entity->setCreatedAt(new \DateTimeImmutable((string) $row['created_at']));
                    if (!empty($row['updated_at'])) {
                        $entity->setUpdatedAt(new \DateTimeImmutable((string) $row['updated_at']));
                    }
                    $entityMap[$id] = $entity;
                }

                // Batch load attributes across value tables
                $this->populateAttributesForEntities($entityMap, $entityType);

                // Index chunk into strategies
                $chunk = array_values($entityMap);
                foreach ($strategies as $strategy) {
                    $strategy->batchIndex($chunk, $entityType);
                }

                $totalIndexed += count($chunk);
                $offset += $batchSize;
            }
        }

        $this->eventDispatcher->dispatch(new FlatTableReindexedEvent($entityTypeCode, $totalIndexed));
        $this->logger->info(sprintf('Flat projection batch re-indexing completed. Total entities indexed: %d.', $totalIndexed));

        return $totalIndexed;
    }

    /**
     * @param array<int, EavEntity> $entityMap
     */
    private function populateAttributesForEntities(array $entityMap, EntityTypeDefinition $entityType): void
    {
        if (empty($entityMap)) {
            return;
        }

        $entityIds = array_keys($entityMap);
        $attrTable = $this->tableConfig->getAttributesTable();

        foreach (StorageBucket::cases() as $bucket) {
            $valTable = $this->tableConfig->getValueTableName($bucket);
            try {
                $rows = $this->connection->fetchAllAssociative(
                    sprintf(
                        'SELECT v.entity_id, a.code as attr_code, v.value
                         FROM %s v
                         INNER JOIN %s a ON a.id = v.attribute_id
                         WHERE v.entity_id IN (%s)',
                        $valTable,
                        $attrTable,
                        implode(',', $entityIds),
                    ),
                );

                foreach ($rows as $row) {
                    $entId = (int) $row['entity_id'];
                    $attrCode = (string) $row['attr_code'];
                    $val = $row['value'];

                    if (isset($entityMap[$entId])) {
                        $entityMap[$entId]->set($attrCode, $val);
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }
    }
}
