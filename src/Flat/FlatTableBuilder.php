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

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Schema\TableConfig;
use WireUpDev\Peav\Type\StorageBucket;

/**
 * Builds Doctrine DBAL physical flat table representations for entity types based on their preset attributes.
 */
class FlatTableBuilder
{
    public function __construct(
        private readonly TableConfig $tableConfig = new TableConfig(),
        private readonly FlatConfig $flatConfig = new FlatConfig(),
    ) {
    }

    /**
     * Builds a physical flat Table definition for the specified entity type.
     */
    public function buildTable(EntityTypeDefinition $entityType): Table
    {
        $tableName = $this->tableConfig->getFlatTableName($entityType->getCode());
        $table = new Table($tableName);

        $table->addColumn('entity_id', Types::BIGINT, ['unsigned' => true]);
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);

        $table->setPrimaryKey(['entity_id']);

        foreach ($entityType->getPresets() as $preset) {
            $attribute = $preset->getAttribute();
            $code = $attribute->getCode();
            $type = $attribute->getType();
            $bucket = $type->getStorageBucket();
            $dbalType = $type->getDbalTypeName();

            match ($bucket) {
                StorageBucket::String => $table->addColumn($code, $dbalType, ['length' => 255, 'notnull' => false]),
                StorageBucket::Integer => $table->addColumn($code, $dbalType, ['notnull' => false]),
                StorageBucket::Decimal => $table->addColumn($code, $dbalType, ['precision' => 18, 'scale' => 4, 'notnull' => false]),
                StorageBucket::DateTime => $table->addColumn($code, $dbalType, ['notnull' => false]),
                StorageBucket::Boolean => $table->addColumn($code, $dbalType, ['notnull' => false]),
                StorageBucket::Text => $table->addColumn($code, $dbalType, ['notnull' => false]),
                StorageBucket::Json => $table->addColumn($code, $dbalType, ['notnull' => false]),
                StorageBucket::Blob => $table->addColumn($code, $dbalType, ['notnull' => false]),
            };

            // Add index for filterable scalar columns
            if (in_array($bucket, [
                StorageBucket::String,
                StorageBucket::Integer,
                StorageBucket::Decimal,
                StorageBucket::DateTime,
                StorageBucket::Boolean,
            ], true)) {
                $table->addIndex([$code], sprintf('idx_%s_%s', $entityType->getCode(), $code));
            }
        }

        return $table;
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
