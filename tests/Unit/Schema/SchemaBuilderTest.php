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

namespace WireUpDev\Peav\Tests\Unit\Schema;

use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use WireUpDev\Peav\Schema\SchemaBuilder;
use WireUpDev\Peav\Schema\TableConfig;
use WireUpDev\Peav\Type\StorageBucket;

final class SchemaBuilderTest extends TestCase
{
    public function testBuildsCompleteEavSchema(): void
    {
        $config = new TableConfig();
        $builder = new SchemaBuilder($config);
        $schema = $builder->buildSchema();

        self::assertInstanceOf(Schema::class, $schema);

        // Core infrastructure tables
        self::assertTrue($schema->hasTable('eav_entity_types'));
        self::assertTrue($schema->hasTable('eav_attributes'));
        self::assertTrue($schema->hasTable('eav_entity_type_attributes'));
        self::assertTrue($schema->hasTable('eav_entities'));

        // All 8 typed value tables
        foreach (StorageBucket::cases() as $bucket) {
            $tableName = $config->getValueTableName($bucket);
            self::assertTrue($schema->hasTable($tableName), "Expected table {$tableName} to exist");

            $table = $schema->getTable($tableName);
            self::assertTrue($table->hasColumn('id'));
            self::assertTrue($table->hasColumn('entity_id'));
            self::assertTrue($table->hasColumn('attribute_id'));
            self::assertTrue($table->hasColumn('value'));
        }
    }

    public function testTableRelationshipsAndUniqueConstraints(): void
    {
        $config = new TableConfig();
        $builder = new SchemaBuilder($config);
        $schema = $builder->buildSchema();

        $entityTypesTable = $schema->getTable('eav_entity_types');
        self::assertTrue($entityTypesTable->hasIndex('uniq_eav_entity_types_code'));

        $attributesTable = $schema->getTable('eav_attributes');
        self::assertTrue($attributesTable->hasIndex('uniq_eav_attributes_code'));

        $presetTable = $schema->getTable('eav_entity_type_attributes');
        self::assertTrue($presetTable->hasIndex('uniq_eav_eta_type_attr'));
        self::assertCount(2, $presetTable->getForeignKeys());

        $stringValTable = $schema->getTable('eav_values_string');
        self::assertTrue($stringValTable->hasIndex('uniq_eav_values_string_ent_attr'));
        self::assertCount(2, $stringValTable->getForeignKeys());
    }
}
