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

namespace WireUpDev\Peav\Tests\Unit\Flat;

use PHPUnit\Framework\TestCase;
use WireUpDev\Peav\Flat\FlatConfig;
use WireUpDev\Peav\Flat\FlatTableBuilder;
use WireUpDev\Peav\Model\AttributeDefinition;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Model\PresetAttribute;
use WireUpDev\Peav\Schema\TableConfig;
use WireUpDev\Peav\Type\AttributeType;

final class FlatTableBuilderTest extends TestCase
{
    public function testBuildsPhysicalFlatTable(): void
    {
        $tableConfig = new TableConfig();
        $flatConfig = new FlatConfig();
        $builder = new FlatTableBuilder($tableConfig, $flatConfig);

        $sku = new AttributeDefinition('sku', AttributeType::String, 'SKU');
        $price = new AttributeDefinition('price', AttributeType::Decimal, 'Price');
        $isActive = new AttributeDefinition('is_active', AttributeType::Boolean, 'Active');
        $tags = new AttributeDefinition('tags', AttributeType::Json, 'Tags');

        $entityType = new EntityTypeDefinition('product', 'Product', presets: [
            new PresetAttribute($sku, isRequired: true),
            new PresetAttribute($price, defaultValue: '0.00'),
            new PresetAttribute($isActive, defaultValue: true),
            new PresetAttribute($tags),
        ]);

        $table = $builder->buildTable($entityType);

        self::assertSame('eav_flat_product', $table->getName());
        self::assertTrue($table->hasColumn('entity_id'));
        self::assertTrue($table->hasColumn('created_at'));
        self::assertTrue($table->hasColumn('updated_at'));
        self::assertTrue($table->hasColumn('sku'));
        self::assertTrue($table->hasColumn('price'));
        self::assertTrue($table->hasColumn('is_active'));
        self::assertTrue($table->hasColumn('tags'));

        self::assertNotNull($table->getPrimaryKey());
        self::assertSame(['entity_id'], $table->getPrimaryKey()->getColumns());
    }
}
