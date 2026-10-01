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

namespace WireUpDev\Peav\Schema;

use WireUpDev\Peav\Type\StorageBucket;

/**
 * Immutable configuration defining table naming conventions and prefixes for EAV schema.
 */
final readonly class TableConfig
{
    public function __construct(
        private string $prefix = 'eav_',
        private string $flatPrefix = 'eav_flat_',
        private string $viewPrefix = 'eav_view_',
        private ?string $entityTypesTable = null,
        private ?string $attributesTable = null,
        private ?string $entityTypeAttributesTable = null,
        private ?string $entitiesTable = null,
        /** @var array<string, string> */
        private array $valueTableOverrides = [],
    ) {
    }

    public function getEntityTypesTable(): string
    {
        return $this->entityTypesTable ?? ($this->prefix . 'entity_types');
    }

    public function getAttributesTable(): string
    {
        return $this->attributesTable ?? ($this->prefix . 'attributes');
    }

    public function getEntityTypeAttributesTable(): string
    {
        return $this->entityTypeAttributesTable ?? ($this->prefix . 'entity_type_attributes');
    }

    public function getEntitiesTable(): string
    {
        return $this->entitiesTable ?? ($this->prefix . 'entities');
    }

    public function getValueTableName(StorageBucket $bucket): string
    {
        return $this->valueTableOverrides[$bucket->value] ?? ($this->prefix . 'values_' . $bucket->getTableSuffix());
    }

    public function getFlatTableName(string $entityTypeCode): string
    {
        return $this->flatPrefix . $entityTypeCode;
    }

    public function getFlatViewName(string $entityTypeCode): string
    {
        return $this->viewPrefix . $entityTypeCode;
    }

    /**
     * Returns a list of all standard EAV table names defined by this configuration.
     *
     * @return list<string>
     */
    public function getAllStandardTableNames(): array
    {
        $tables = [
            $this->getEntityTypesTable(),
            $this->getAttributesTable(),
            $this->getEntityTypeAttributesTable(),
            $this->getEntitiesTable(),
        ];

        foreach (StorageBucket::cases() as $bucket) {
            $tables[] = $this->getValueTableName($bucket);
        }

        return $tables;
    }
}
