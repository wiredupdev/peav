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
use Doctrine\DBAL\Types\Types;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use WireUpDev\Peav\Event\AttributeDeletedEvent;
use WireUpDev\Peav\Event\AttributeSavedEvent;
use WireUpDev\Peav\Event\EntityTypePresetUpdatedEvent;
use WireUpDev\Peav\Exception\AttributeInUseException;
use WireUpDev\Peav\Exception\AttributeNotFoundException;
use WireUpDev\Peav\Exception\EntityTypeNotFoundException;
use WireUpDev\Peav\Factory\NullAdapters\NullCache;
use WireUpDev\Peav\Factory\NullAdapters\NullEventDispatcher;
use WireUpDev\Peav\Model\AttributeDefinition;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Model\PresetAttribute;
use WireUpDev\Peav\Schema\TableConfig;
use WireUpDev\Peav\Type\StorageBucket;
use WireUpDev\Peav\Type\TypeRegistry;

/**
 * Concrete repository managing reusable attribute definitions and entity type preset configurations.
 */
class AttributeRepository implements AttributeRepositoryInterface
{
    private readonly CacheInterface $cache;
    private readonly EventDispatcherInterface $eventDispatcher;
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly Connection $connection,
        private readonly TypeRegistry $typeRegistry = new TypeRegistry(),
        private readonly TableConfig $tableConfig = new TableConfig(),
        ?CacheInterface $cache = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->cache = $cache ?? new NullCache();
        $this->eventDispatcher = $eventDispatcher ?? new NullEventDispatcher();
        $this->logger = $logger ?? new NullLogger();
    }

    public function saveAttribute(AttributeDefinition $attribute): void
    {
        $table = $this->tableConfig->getAttributesTable();
        $now = new \DateTimeImmutable();
        $existingId = $this->getAttributeIdByCode($attribute->getCode());

        $validationRules = $attribute->getValidationRules();
        $validationJson = !empty($validationRules) ? json_encode($validationRules, JSON_THROW_ON_ERROR) : null;

        $data = [
            'code' => $attribute->getCode(),
            'type' => $attribute->getType()->getName(),
            'name' => $attribute->getName(),
            'description' => $attribute->getDescription(),
            'validation_rules' => $validationJson,
        ];

        if ($existingId !== null) {
            $data['updated_at'] = $now->format('Y-m-d H:i:s');
            $this->connection->update($table, $data, ['id' => $existingId]);
            $this->logger->info(sprintf('Updated attribute definition "%s".', $attribute->getCode()));
        } else {
            $data['created_at'] = $now->format('Y-m-d H:i:s');
            $this->connection->insert($table, $data);
            $this->logger->info(sprintf('Created attribute definition "%s".', $attribute->getCode()));
        }

        $this->invalidateAttributeCache($attribute->getCode());
        $this->eventDispatcher->dispatch(new AttributeSavedEvent($attribute));
    }

    public function getAttribute(string $code): ?AttributeDefinition
    {
        $cacheKey = $this->getAttrCacheKey($code);
        $cached = $this->cache->get($cacheKey);

        if ($cached instanceof AttributeDefinition) {
            $this->logger->debug(sprintf('Attribute cache hit for "%s".', $code));

            return $cached;
        }

        $table = $this->tableConfig->getAttributesTable();
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT * FROM %s WHERE code = ?', $table),
            [$code],
        );

        if (!$row) {
            $this->logger->debug(sprintf('Attribute "%s" not found in database.', $code));

            return null;
        }

        $attr = $this->hydrateAttribute($row);
        $this->cache->set($cacheKey, $attr);

        return $attr;
    }

    public function getAttributes(array $codes): array
    {
        $attributes = [];
        foreach ($codes as $code) {
            $attr = $this->getAttribute($code);
            if ($attr !== null) {
                $attributes[$code] = $attr;
            }
        }

        return $attributes;
    }

    public function getAllAttributes(): array
    {
        $table = $this->tableConfig->getAttributesTable();
        $rows = $this->connection->fetchAllAssociative(sprintf('SELECT * FROM %s ORDER BY code ASC', $table));

        $attributes = [];
        foreach ($rows as $row) {
            $attr = $this->hydrateAttribute($row);
            $attributes[$attr->getCode()] = $attr;
            $this->cache->set($this->getAttrCacheKey($attr->getCode()), $attr);
        }

        return $attributes;
    }

    public function isAttributeInUse(string $code): bool
    {
        $attrId = $this->getAttributeIdByCode($code);
        if ($attrId === null) {
            return false;
        }

        foreach (StorageBucket::cases() as $bucket) {
            $valTable = $this->tableConfig->getValueTableName($bucket);
            try {
                $count = (int) $this->connection->fetchOne(
                    sprintf('SELECT COUNT(*) FROM %s WHERE attribute_id = ?', $valTable),
                    [$attrId],
                );

                if ($count > 0) {
                    return true;
                }
            } catch (\Throwable) {
                // Table might not exist yet
                continue;
            }
        }

        return false;
    }

    public function deleteAttribute(string $code): void
    {
        $attribute = $this->getAttribute($code);
        if ($attribute === null) {
            throw new AttributeNotFoundException(sprintf('Attribute "%s" not found.', $code));
        }

        if ($this->isAttributeInUse($code)) {
            throw new AttributeInUseException(sprintf('Cannot delete attribute "%s" because it is in use by entities across value tables.', $code));
        }

        $attrId = $this->getAttributeIdByCode($code);
        $attrTable = $this->tableConfig->getAttributesTable();
        $etaTable = $this->tableConfig->getEntityTypeAttributesTable();

        $this->connection->transactional(function () use ($attrId, $attrTable, $etaTable): void {
            // Remove preset associations
            $this->connection->delete($etaTable, ['attribute_id' => $attrId]);
            // Delete attribute definition
            $this->connection->delete($attrTable, ['id' => $attrId]);
        });

        $this->logger->info(sprintf('Safely deleted unused attribute "%s".', $code));
        $this->invalidateAttributeCache($code);
        $this->eventDispatcher->dispatch(new AttributeDeletedEvent($attribute));
    }

    public function saveEntityType(EntityTypeDefinition $entityType): void
    {
        $table = $this->tableConfig->getEntityTypesTable();
        $now = new \DateTimeImmutable();
        $existingId = $this->getEntityTypeIdByCode($entityType->getCode());

        $data = [
            'code' => $entityType->getCode(),
            'name' => $entityType->getName(),
            'description' => $entityType->getDescription(),
            'custom_class' => $entityType->getCustomClass(),
        ];

        $this->connection->transactional(function () use ($table, $data, $existingId, $entityType, $now): void {
            if ($existingId !== null) {
                $data['updated_at'] = $now->format('Y-m-d H:i:s');
                $this->connection->update($table, $data, ['id' => $existingId]);
                $entityTypeId = $existingId;
            } else {
                $data['created_at'] = $now->format('Y-m-d H:i:s');
                $this->connection->insert($table, $data);
                $entityTypeId = (int) $this->connection->lastInsertId();
            }

            // Sync preset attributes
            $etaTable = $this->tableConfig->getEntityTypeAttributesTable();
            $this->connection->delete($etaTable, ['entity_type_id' => $entityTypeId]);

            foreach ($entityType->getPresets() as $preset) {
                $attrId = $this->getAttributeIdByCode($preset->getAttributeCode());
                if ($attrId === null) {
                    $this->saveAttribute($preset->getAttribute());
                    $attrId = $this->getAttributeIdByCode($preset->getAttributeCode());
                }

                $metaJson = !empty($preset->getCustomMetadata()) ? json_encode($preset->getCustomMetadata(), JSON_THROW_ON_ERROR) : null;
                $defaultVal = $preset->getDefaultValue();
                if (is_array($defaultVal) || is_object($defaultVal)) {
                    $defaultVal = json_encode($defaultVal, JSON_THROW_ON_ERROR);
                } elseif ($defaultVal !== null) {
                    $defaultVal = (string) $defaultVal;
                }

                $this->connection->insert($etaTable, [
                    'entity_type_id' => $entityTypeId,
                    'attribute_id' => $attrId,
                    'is_required' => $preset->isRequired(),
                    'default_value' => $defaultVal,
                    'position' => $preset->getPosition(),
                    'custom_metadata' => $metaJson,
                ], [
                    'is_required' => Types::BOOLEAN,
                    'position' => Types::INTEGER,
                ]);
            }
        });

        $this->logger->info(sprintf('Saved entity type definition "%s" with %d presets.', $entityType->getCode(), count($entityType->getPresets())));
        $this->invalidateEntityTypeCache($entityType->getCode());
        $this->eventDispatcher->dispatch(new EntityTypePresetUpdatedEvent($entityType));
    }

    public function getEntityType(string $code): ?EntityTypeDefinition
    {
        $cacheKey = $this->getEntityTypeCacheKey($code);
        $cached = $this->cache->get($cacheKey);

        if ($cached instanceof EntityTypeDefinition) {
            $this->logger->debug(sprintf('Entity type cache hit for "%s".', $code));

            return $cached;
        }

        $table = $this->tableConfig->getEntityTypesTable();
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT * FROM %s WHERE code = ?', $table),
            [$code],
        );

        if (!$row) {
            $this->logger->debug(sprintf('Entity type "%s" not found.', $code));

            return null;
        }

        $entityTypeId = (int) $row['id'];
        $presets = $this->loadPresetsForEntityTypeId($entityTypeId);

        /** @var class-string<\WireUpDev\Peav\Model\EavEntity>|null $customClass */
        $customClass = $row['custom_class'] ?? null;

        $entityTypeDef = new EntityTypeDefinition(
            (string) $row['code'],
            (string) $row['name'],
            $row['description'] !== null ? (string) $row['description'] : null,
            $customClass,
            $presets,
        );

        $this->cache->set($cacheKey, $entityTypeDef);

        return $entityTypeDef;
    }

    public function getAllEntityTypes(): array
    {
        $table = $this->tableConfig->getEntityTypesTable();
        $rows = $this->connection->fetchAllAssociative(sprintf('SELECT code FROM %s ORDER BY code ASC', $table));

        $entityTypes = [];
        foreach ($rows as $row) {
            $code = (string) $row['code'];
            $def = $this->getEntityType($code);
            if ($def !== null) {
                $entityTypes[$code] = $def;
            }
        }

        return $entityTypes;
    }

    public function deleteEntityType(string $code): void
    {
        $typeId = $this->getEntityTypeIdByCode($code);
        if ($typeId === null) {
            throw new EntityTypeNotFoundException(sprintf('Entity type "%s" not found.', $code));
        }

        $table = $this->tableConfig->getEntityTypesTable();
        $this->connection->delete($table, ['id' => $typeId]);

        $this->logger->info(sprintf('Deleted entity type "%s".', $code));
        $this->invalidateEntityTypeCache($code);
    }

    public function attachPreset(string $entityTypeCode, PresetAttribute $preset): void
    {
        $entityType = $this->getEntityType($entityTypeCode);
        if ($entityType === null) {
            throw new EntityTypeNotFoundException(sprintf('Entity type "%s" not found.', $entityTypeCode));
        }

        $updated = $entityType->withPreset($preset);
        $this->saveEntityType($updated);
    }

    public function detachPreset(string $entityTypeCode, string $attributeCode): void
    {
        $entityType = $this->getEntityType($entityTypeCode);
        if ($entityType === null) {
            throw new EntityTypeNotFoundException(sprintf('Entity type "%s" not found.', $entityTypeCode));
        }

        $updated = $entityType->withoutPreset($attributeCode);
        $this->saveEntityType($updated);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateAttribute(array $row): AttributeDefinition
    {
        $type = $this->typeRegistry->resolve((string) $row['type']);
        $validationRules = [];

        if (!empty($row['validation_rules'])) {
            try {
                $validationRules = (array) json_decode((string) $row['validation_rules'], true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $validationRules = [];
            }
        }

        return new AttributeDefinition(
            (string) $row['code'],
            $type,
            (string) $row['name'],
            $row['description'] !== null ? (string) $row['description'] : null,
            $validationRules,
        );
    }

    /**
     * @return list<PresetAttribute>
     */
    private function loadPresetsForEntityTypeId(int $entityTypeId): array
    {
        $etaTable = $this->tableConfig->getEntityTypeAttributesTable();
        $attrTable = $this->tableConfig->getAttributesTable();

        $rows = $this->connection->fetchAllAssociative(
            sprintf(
                'SELECT eta.*, a.code as attr_code, a.type as attr_type, a.name as attr_name, a.description as attr_desc, a.validation_rules as attr_rules
                 FROM %s eta
                 INNER JOIN %s a ON a.id = eta.attribute_id
                 WHERE eta.entity_type_id = ?
                 ORDER BY eta.position ASC, eta.id ASC',
                $etaTable,
                $attrTable,
            ),
            [$entityTypeId],
        );

        $presets = [];
        foreach ($rows as $row) {
            $attrRow = [
                'code' => $row['attr_code'],
                'type' => $row['attr_type'],
                'name' => $row['attr_name'],
                'description' => $row['attr_desc'],
                'validation_rules' => $row['attr_rules'],
            ];

            $attribute = $this->hydrateAttribute($attrRow);
            $customMetadata = [];
            if (!empty($row['custom_metadata'])) {
                try {
                    $customMetadata = (array) json_decode((string) $row['custom_metadata'], true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable) {
                    $customMetadata = [];
                }
            }

            $rawDefault = $row['default_value'];
            $defaultVal = null;
            if ($rawDefault !== null) {
                $bucket = $attribute->getType()->getStorageBucket();
                $defaultVal = match ($bucket) {
                    StorageBucket::Integer => (int) $rawDefault,
                    StorageBucket::Decimal, StorageBucket::String, StorageBucket::Text => (string) $rawDefault,
                    StorageBucket::Boolean => filter_var($rawDefault, FILTER_VALIDATE_BOOLEAN),
                    StorageBucket::Json => json_decode((string) $rawDefault, true),
                    default => $rawDefault,
                };
            }

            $presets[] = new PresetAttribute(
                $attribute,
                (bool) $row['is_required'],
                $defaultVal,
                (int) $row['position'],
                $customMetadata,
            );
        }

        return $presets;
    }

    private function getAttributeIdByCode(string $code): ?int
    {
        $table = $this->tableConfig->getAttributesTable();
        $id = $this->connection->fetchOne(sprintf('SELECT id FROM %s WHERE code = ?', $table), [$code]);

        return $id !== false && $id !== null ? (int) $id : null;
    }

    private function getEntityTypeIdByCode(string $code): ?int
    {
        $table = $this->tableConfig->getEntityTypesTable();
        $id = $this->connection->fetchOne(sprintf('SELECT id FROM %s WHERE code = ?', $table), [$code]);

        return $id !== false && $id !== null ? (int) $id : null;
    }

    private function getAttrCacheKey(string $code): string
    {
        return 'peav_attr_' . md5($code);
    }

    private function getEntityTypeCacheKey(string $code): string
    {
        return 'peav_type_' . md5($code);
    }

    private function invalidateAttributeCache(string $code): void
    {
        $this->cache->delete($this->getAttrCacheKey($code));
        $this->cache->delete('peav_attr_all');
    }

    private function invalidateEntityTypeCache(string $code): void
    {
        $this->cache->delete($this->getEntityTypeCacheKey($code));
        $this->cache->delete('peav_type_all');
    }
}
