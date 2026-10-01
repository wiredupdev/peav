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

namespace WireUpDev\Peav\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Types;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WireUpDev\Peav\Caster\CasterRegistry;
use WireUpDev\Peav\Caster\TypeCasterInterface;
use WireUpDev\Peav\Event\EntityCreatedEvent;
use WireUpDev\Peav\Event\EntityCreatingEvent;
use WireUpDev\Peav\Event\EntityDeletedEvent;
use WireUpDev\Peav\Event\EntityDeletingEvent;
use WireUpDev\Peav\Event\EntityLoadedEvent;
use WireUpDev\Peav\Event\EntityUpdatedEvent;
use WireUpDev\Peav\Event\EntityUpdatingEvent;
use WireUpDev\Peav\Exception\EntityTypeNotFoundException;
use WireUpDev\Peav\Exception\InvalidAttributeValueException;
use WireUpDev\Peav\Factory\NullAdapters\NullEventDispatcher;
use WireUpDev\Peav\Flat\FlatIndexerInterface;
use WireUpDev\Peav\Model\EavEntity;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Schema\TableConfig;
use WireUpDev\Peav\Type\StorageBucket;
use WireUpDev\Peav\Type\TypeRegistry;

/**
 * High-performance, transactional repository for persisting and hydrating EAV entities.
 */
class EavRepository implements EavRepositoryInterface
{
    private readonly EventDispatcherInterface $eventDispatcher;
    private readonly LoggerInterface $logger;
    private readonly CasterRegistry $casterRegistry;

    public function __construct(
        private readonly Connection $connection,
        private readonly AttributeRepositoryInterface $attributeRepository,
        private readonly TypeRegistry $typeRegistry = new TypeRegistry(),
        private readonly ?FlatIndexerInterface $flatIndexer = null,
        private readonly TableConfig $tableConfig = new TableConfig(),
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
        ?CasterRegistry $casterRegistry = null,
    ) {
        $this->eventDispatcher = $eventDispatcher ?? new NullEventDispatcher();
        $this->logger = $logger ?? new NullLogger();
        $this->casterRegistry = $casterRegistry ?? new CasterRegistry();
    }

    public function createEntity(string $entityTypeCode, array $attributes = []): EavEntity
    {
        $entityType = $this->attributeRepository->getEntityType($entityTypeCode);
        if ($entityType === null) {
            throw new EntityTypeNotFoundException(sprintf('Entity type "%s" is not registered.', $entityTypeCode));
        }

        // Apply preset default values for missing attributes
        $mergedAttributes = $attributes;
        foreach ($entityType->getPresets() as $preset) {
            $code = $preset->getAttributeCode();
            if (!array_key_exists($code, $mergedAttributes) && $preset->getDefaultValue() !== null) {
                $mergedAttributes[$code] = $preset->getDefaultValue();
            }
        }

        $customClass = $entityType->getCustomClass();
        if ($customClass !== null && is_subclass_of($customClass, EavEntity::class)) {
            return new $customClass($entityTypeCode, null, $mergedAttributes);
        }

        return new EavEntity($entityTypeCode, null, $mergedAttributes);
    }

    public function save(EavEntity $entity): void
    {
        $entityType = $this->attributeRepository->getEntityType($entity->getEntityType());
        if ($entityType === null) {
            throw new EntityTypeNotFoundException(sprintf('Entity type "%s" is not registered.', $entity->getEntityType()));
        }

        // Apply defaults and validate required presets
        $this->applyPresetDefaultsAndValidate($entity, $entityType);

        $isNew = $entity->getId() === null;

        if ($isNew) {
            $creatingEvent = new EntityCreatingEvent($entity);
            $this->eventDispatcher->dispatch($creatingEvent);
            if ($creatingEvent->isPropagationStopped()) {
                $this->logger->warning(sprintf('Creation of entity [%s] was stopped by event listener.', $entity->getEntityType()));

                return;
            }
        } else {
            $updatingEvent = new EntityUpdatingEvent($entity);
            $this->eventDispatcher->dispatch($updatingEvent);
            if ($updatingEvent->isPropagationStopped()) {
                $this->logger->warning(sprintf('Update of entity [%s #%s] was stopped by event listener.', $entity->getEntityType(), (string) $entity->getId()));

                return;
            }
        }

        $this->connection->transactional(function () use ($entity, $entityType, $isNew): void {
            $this->persistEntityRecord($entity, $entityType, $isNew);
            $this->persistEntityAttributes($entity, $entityType);
        });

        // Trigger real-time flat indexing
        if ($this->flatIndexer !== null) {
            $this->flatIndexer->indexEntity($entity, $entityType);
        }

        if ($isNew) {
            $this->eventDispatcher->dispatch(new EntityCreatedEvent($entity));
            $this->logger->info(sprintf('Persisted new entity [%s #%s].', $entity->getEntityType(), (string) $entity->getId()));
        } else {
            $this->eventDispatcher->dispatch(new EntityUpdatedEvent($entity));
            $this->logger->info(sprintf('Updated existing entity [%s #%s].', $entity->getEntityType(), (string) $entity->getId()));
        }
    }

    public function saveMany(array $entities): void
    {
        if (empty($entities)) {
            return;
        }

        $this->connection->transactional(function () use ($entities): void {
            foreach ($entities as $entity) {
                $this->save($entity);
            }
        });
    }

    public function find(string $entityTypeCode, int|string $id): ?EavEntity
    {
        $entities = $this->findMany($entityTypeCode, [$id]);

        return $entities[$id] ?? null;
    }

    public function findMany(string $entityTypeCode, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $entityType = $this->attributeRepository->getEntityType($entityTypeCode);
        if ($entityType === null) {
            throw new EntityTypeNotFoundException(sprintf('Entity type "%s" is not registered.', $entityTypeCode));
        }

        $entitiesTable = $this->tableConfig->getEntitiesTable();
        $entityTypesTable = $this->tableConfig->getEntityTypesTable();

        $typeId = (int) $this->connection->fetchOne(
            sprintf('SELECT id FROM %s WHERE code = ?', $entityTypesTable),
            [$entityTypeCode],
        );

        if ($typeId === 0) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT id, created_at, updated_at FROM %s WHERE entity_type_id = ? AND id IN (%s)', $entitiesTable, implode(',', array_map('intval', $ids))),
            [$typeId],
        );

        if (empty($rows)) {
            return [];
        }

        $customClass = $entityType->getCustomClass();
        $entities = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if ($customClass !== null && is_subclass_of($customClass, EavEntity::class)) {
                $entity = new $customClass($entityTypeCode, $id);
            } else {
                $entity = new EavEntity($entityTypeCode, $id);
            }

            $entity->setCreatedAt(new \DateTimeImmutable((string) $row['created_at']));
            if (!empty($row['updated_at'])) {
                $entity->setUpdatedAt(new \DateTimeImmutable((string) $row['updated_at']));
            }

            $entities[$id] = $entity;
        }

        // Batch hydrate attributes across all 8 buckets in O(T) queries
        $this->batchHydrateAttributes($entities, array_keys($entities));

        foreach ($entities as $entity) {
            $this->eventDispatcher->dispatch(new EntityLoadedEvent($entity));
        }

        return $entities;
    }

    public function delete(EavEntity $entity): void
    {
        $id = $entity->getId();
        if ($id === null) {
            return;
        }

        $entityType = $this->attributeRepository->getEntityType($entity->getEntityType());
        if ($entityType === null) {
            throw new EntityTypeNotFoundException(sprintf('Entity type "%s" is not registered.', $entity->getEntityType()));
        }

        $deletingEvent = new EntityDeletingEvent($entity);
        $this->eventDispatcher->dispatch($deletingEvent);
        if ($deletingEvent->isPropagationStopped()) {
            $this->logger->warning(sprintf('Deletion of entity [%s #%s] was stopped by event listener.', $entity->getEntityType(), (string) $id));

            return;
        }

        $entitiesTable = $this->tableConfig->getEntitiesTable();
        $this->connection->delete($entitiesTable, ['id' => $id]);

        // Remove from flat projections
        if ($this->flatIndexer !== null) {
            $this->flatIndexer->removeEntity($id, $entityType);
        }

        $this->eventDispatcher->dispatch(new EntityDeletedEvent($entity));
        $this->logger->info(sprintf('Deleted entity [%s #%s].', $entity->getEntityType(), (string) $id));
    }

    public function deleteById(string $entityTypeCode, int|string $id): void
    {
        $entity = $this->find($entityTypeCode, $id);
        if ($entity !== null) {
            $this->delete($entity);
        } else {
            $entityStub = new EavEntity($entityTypeCode, $id);
            $this->delete($entityStub);
        }
    }

    private function applyPresetDefaultsAndValidate(EavEntity $entity, EntityTypeDefinition $entityType): void
    {
        foreach ($entityType->getPresets() as $preset) {
            $code = $preset->getAttributeCode();
            $val = $entity->get($code);

            if ($val === null && $preset->getDefaultValue() !== null) {
                $entity->set($code, $preset->getDefaultValue());
                $val = $preset->getDefaultValue();
            }

            if ($preset->isRequired() && ($val === null || $val === '')) {
                throw new InvalidAttributeValueException(sprintf('Preset attribute "%s" is required for entity type "%s".', $code, $entityType->getCode()));
            }
        }
    }

    private function persistEntityRecord(EavEntity $entity, EntityTypeDefinition $entityType, bool $isNew): void
    {
        $entitiesTable = $this->tableConfig->getEntitiesTable();
        $entityTypesTable = $this->tableConfig->getEntityTypesTable();
        $now = new \DateTimeImmutable();

        $typeId = (int) $this->connection->fetchOne(
            sprintf('SELECT id FROM %s WHERE code = ?', $entityTypesTable),
            [$entityType->getCode()],
        );

        if ($typeId === 0) {
            throw new EntityTypeNotFoundException(sprintf('Entity type "%s" not found in database.', $entityType->getCode()));
        }

        if ($isNew) {
            $createdAt = $entity->getCreatedAt() ?? $now;
            $this->connection->insert($entitiesTable, [
                'entity_type_id' => $typeId,
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
                'updated_at' => $entity->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ]);
            $entityId = (int) $this->connection->lastInsertId();
            $entity->setId($entityId);
            $entity->setCreatedAt($createdAt);
        } else {
            $updatedAt = $now;
            $this->connection->update($entitiesTable, [
                'updated_at' => $updatedAt->format('Y-m-d H:i:s'),
            ], ['id' => $entity->getId()]);
            $entity->setUpdatedAt($updatedAt);
        }
    }

    private function persistEntityAttributes(EavEntity $entity, EntityTypeDefinition $entityType): void
    {
        $entityId = $entity->getId();
        $attributes = $entity->all();
        $platform = $this->connection->getDatabasePlatform();

        // Group attributes by storage bucket
        /** @var array<string, list<array{attr_id: int, value: mixed, type: \WireUpDev\Peav\Type\TypeInterface}>> $bucketRows */
        $bucketRows = [];

        foreach ($attributes as $attrCode => $rawValue) {
            $attrDef = $this->attributeRepository->getAttribute($attrCode);
            if ($attrDef === null) {
                continue;
            }

            $type = $attrDef->getType();
            $bucket = $type->getStorageBucket();
            $caster = $this->casterRegistry->getForType($type);

            $dbValue = $caster->convertToDatabaseValue($rawValue, $type, $platform);
            $attrId = $this->getAttributeIdByCode($attrCode);

            if ($attrId === null) {
                continue;
            }

            $bucketRows[$bucket->value][] = [
                'attr_id' => $attrId,
                'value' => $dbValue,
                'type' => $type,
            ];
        }

        // Delete existing rows for this entity across all buckets then insert fresh
        foreach (StorageBucket::cases() as $bucket) {
            $valTable = $this->tableConfig->getValueTableName($bucket);
            $this->connection->delete($valTable, ['entity_id' => $entityId]);

            if (isset($bucketRows[$bucket->value])) {
                foreach ($bucketRows[$bucket->value] as $item) {
                    if ($item['value'] !== null) {
                        $this->connection->insert($valTable, [
                            'entity_id' => $entityId,
                            'attribute_id' => $item['attr_id'],
                            'value' => $item['value'],
                        ]);
                    }
                }
            }
        }
    }

    /**
     * @param array<int|string, EavEntity> $entities
     * @param list<int|string> $entityIds
     */
    private function batchHydrateAttributes(array $entities, array $entityIds): void
    {
        $attrTable = $this->tableConfig->getAttributesTable();
        $platform = $this->connection->getDatabasePlatform();

        foreach (StorageBucket::cases() as $bucket) {
            $valTable = $this->tableConfig->getValueTableName($bucket);

            try {
                $rows = $this->connection->fetchAllAssociative(
                    sprintf(
                        'SELECT v.entity_id, a.code as attr_code, a.type as attr_type, v.value
                         FROM %s v
                         INNER JOIN %s a ON a.id = v.attribute_id
                         WHERE v.entity_id IN (%s)',
                        $valTable,
                        $attrTable,
                        implode(',', array_map('intval', $entityIds)),
                    ),
                );

                foreach ($rows as $row) {
                    $entId = (int) $row['entity_id'];
                    $attrCode = (string) $row['attr_code'];
                    $type = $this->typeRegistry->resolve((string) $row['attr_type']);
                    $caster = $this->casterRegistry->getForType($type);

                    $phpValue = $caster->convertToPHPValue($row['value'], $type, $platform);

                    if (isset($entities[$entId])) {
                        $entities[$entId]->set($attrCode, $phpValue);
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }
    }

    private function getAttributeIdByCode(string $code): ?int
    {
        $table = $this->tableConfig->getAttributesTable();
        $id = $this->connection->fetchOne(sprintf('SELECT id FROM %s WHERE code = ?', $table), [$code]);

        return $id !== false && $id !== null ? (int) $id : null;
    }
}
