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
use WireUpDev\Peav\Flat\FlatViewBuilder;
use WireUpDev\Peav\Model\AttributeDefinition;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Model\PresetAttribute;
use WireUpDev\Peav\Schema\TableConfig;
use WireUpDev\Peav\Type\AttributeType;

final class FlatViewBuilderTest extends TestCase
{
    public function testBuildsDynamicSqlViewDdl(): void
    {
        $tableConfig = new TableConfig();
        $flatConfig = new FlatConfig();
        $builder = new FlatViewBuilder($tableConfig, $flatConfig);

        $sku = new AttributeDefinition('sku', AttributeType::String, 'SKU');
        $price = new AttributeDefinition('price', AttributeType::Decimal, 'Price');

        $entityType = new EntityTypeDefinition('product', 'Product', presets: [
            new PresetAttribute($sku),
            new PresetAttribute($price),
        ]);

        $sql = $builder->buildCreateViewSql($entityType);

        self::assertStringContainsString('CREATE VIEW eav_view_product AS', $sql);
        self::assertStringContainsString('e.id AS entity_id', $sql);
        self::assertStringContainsString('e.created_at', $sql);
        self::assertStringContainsString('e.updated_at', $sql);
        self::assertStringContainsString('AS sku', $sql);
        self::assertStringContainsString('AS price', $sql);

        $dropSql = $builder->buildDropViewSql($entityType);
        self::assertSame('DROP VIEW IF EXISTS eav_view_product', $dropSql);
    }
}
