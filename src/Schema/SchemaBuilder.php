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

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use WireUpDev\Peav\Type\StorageBucket;

/**
 * Builds the complete Doctrine DBAL relational schema definition for Peav.
 */
class SchemaBuilder
{
    public function __construct(
        private readonly TableConfig $tableConfig = new TableConfig(),
    ) {
    }

    /**
     * Builds and returns the complete DBAL Schema object.
     */
    public function buildSchema(): Schema
    {
        $schema = new Schema();

        $entityTypesTable = $this->createEntityTypesTable($schema);
        $attributesTable = $this->createAttributesTable($schema);
        $this->createEntityTypeAttributesTable($schema, $entityTypesTable, $attributesTable);
        $entitiesTable = $this->createEntitiesTable($schema, $entityTypesTable);
        $this->createValueTables($schema, $entitiesTable, $attributesTable);

        return $schema;
    }

    public function getTableConfig(): TableConfig
    {
        return $this->tableConfig;
    }

    private function createEntityTypesTable(Schema $schema): Table
    {
        $table = $schema->createTable($this->tableConfig->getEntityTypesTable());

        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'unsigned' => true]);
        $table->addColumn('code', Types::STRING, ['length' => 64]);
        $table->addColumn('name', Types::STRING, ['length' => 255]);
        $table->addColumn('description', Types::TEXT, ['notnull' => false]);
        $table->addColumn('custom_class', Types::STRING, ['length' => 255, 'notnull' => false]);
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['code'], 'uniq_eav_entity_types_code');

        return $table;
    }

    private function createAttributesTable(Schema $schema): Table
    {
        $table = $schema->createTable($this->tableConfig->getAttributesTable());

        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'unsigned' => true]);
        $table->addColumn('code', Types::STRING, ['length' => 64]);
        $table->addColumn('type', Types::STRING, ['length' => 64]);
        $table->addColumn('name', Types::STRING, ['length' => 255]);
        $table->addColumn('description', Types::TEXT, ['notnull' => false]);
        $table->addColumn('validation_rules', Types::JSON, ['notnull' => false]);
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['code'], 'uniq_eav_attributes_code');
        $table->addIndex(['type'], 'idx_eav_attributes_type');

        return $table;
    }

    private function createEntityTypeAttributesTable(
        Schema $schema,
        Table $entityTypesTable,
        Table $attributesTable,
    ): Table {
        $table = $schema->createTable($this->tableConfig->getEntityTypeAttributesTable());

        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'unsigned' => true]);
        $table->addColumn('entity_type_id', Types::BIGINT, ['unsigned' => true]);
        $table->addColumn('attribute_id', Types::BIGINT, ['unsigned' => true]);
        $table->addColumn('is_required', Types::BOOLEAN, ['default' => false]);
        $table->addColumn('default_value', Types::TEXT, ['notnull' => false]);
        $table->addColumn('position', Types::INTEGER, ['default' => 0]);
        $table->addColumn('custom_metadata', Types::JSON, ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['entity_type_id', 'attribute_id'], 'uniq_eav_eta_type_attr');
        $table->addIndex(['entity_type_id', 'position'], 'idx_eav_eta_type_pos');

        $table->addForeignKeyConstraint(
            $entityTypesTable->getName(),
            ['entity_type_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_eav_eta_entity_type',
        );

        $table->addForeignKeyConstraint(
            $attributesTable->getName(),
            ['attribute_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_eav_eta_attribute',
        );

        return $table;
    }

    private function createEntitiesTable(Schema $schema, Table $entityTypesTable): Table
    {
        $table = $schema->createTable($this->tableConfig->getEntitiesTable());

        $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'unsigned' => true]);
        $table->addColumn('entity_type_id', Types::BIGINT, ['unsigned' => true]);
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['entity_type_id'], 'idx_eav_entities_type');
        $table->addIndex(['created_at'], 'idx_eav_entities_created_at');

        $table->addForeignKeyConstraint(
            $entityTypesTable->getName(),
            ['entity_type_id'],
            ['id'],
            ['onDelete' => 'RESTRICT'],
            'fk_eav_entities_type',
        );

        return $table;
    }

    private function createValueTables(Schema $schema, Table $entitiesTable, Table $attributesTable): void
    {
        foreach (StorageBucket::cases() as $bucket) {
            $tableName = $this->tableConfig->getValueTableName($bucket);
            $table = $schema->createTable($tableName);

            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'unsigned' => true]);
            $table->addColumn('entity_id', Types::BIGINT, ['unsigned' => true]);
            $table->addColumn('attribute_id', Types::BIGINT, ['unsigned' => true]);

            // Add value column based on storage bucket
            match ($bucket) {
                StorageBucket::String => $table->addColumn('value', Types::STRING, ['length' => 255, 'notnull' => false]),
                StorageBucket::Integer => $table->addColumn('value', Types::BIGINT, ['notnull' => false]),
                StorageBucket::Decimal => $table->addColumn('value', Types::DECIMAL, ['precision' => 18, 'scale' => 4, 'notnull' => false]),
                StorageBucket::DateTime => $table->addColumn('value', Types::DATETIME_IMMUTABLE, ['notnull' => false]),
                StorageBucket::Boolean => $table->addColumn('value', Types::BOOLEAN, ['notnull' => false]),
                StorageBucket::Text => $table->addColumn('value', Types::TEXT, ['notnull' => false]),
                StorageBucket::Json => $table->addColumn('value', Types::JSON, ['notnull' => false]),
                StorageBucket::Blob => $table->addColumn('value', Types::BLOB, ['notnull' => false]),
            };

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['entity_id', 'attribute_id'], sprintf('uniq_%s_ent_attr', $tableName));

            // Targeted index on (attribute_id, value) for filterable scalar types
            if (in_array($bucket, [
                StorageBucket::String,
                StorageBucket::Integer,
                StorageBucket::Decimal,
                StorageBucket::DateTime,
                StorageBucket::Boolean,
            ], true)) {
                $table->addIndex(['attribute_id', 'value'], sprintf('idx_%s_attr_val', $tableName));
            } else {
                $table->addIndex(['attribute_id'], sprintf('idx_%s_attr', $tableName));
            }

            $table->addForeignKeyConstraint(
                $entitiesTable->getName(),
                ['entity_id'],
                ['id'],
                ['onDelete' => 'CASCADE'],
                sprintf('fk_%s_entity', $tableName),
            );

            $table->addForeignKeyConstraint(
                $attributesTable->getName(),
                ['attribute_id'],
                ['id'],
                ['onDelete' => 'CASCADE'],
                sprintf('fk_%s_attribute', $tableName),
            );
        }
    }
}
