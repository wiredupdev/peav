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

use PHPUnit\Framework\TestCase;
use WireUpDev\Peav\Schema\TableConfig;
use WireUpDev\Peav\Type\StorageBucket;

final class TableConfigTest extends TestCase
{
    public function testDefaultTableNames(): void
    {
        $config = new TableConfig();

        self::assertSame('eav_entity_types', $config->getEntityTypesTable());
        self::assertSame('eav_attributes', $config->getAttributesTable());
        self::assertSame('eav_entity_type_attributes', $config->getEntityTypeAttributesTable());
        self::assertSame('eav_entities', $config->getEntitiesTable());

        self::assertSame('eav_values_string', $config->getValueTableName(StorageBucket::String));
        self::assertSame('eav_values_int', $config->getValueTableName(StorageBucket::Integer));
        self::assertSame('eav_values_decimal', $config->getValueTableName(StorageBucket::Decimal));
        self::assertSame('eav_values_datetime', $config->getValueTableName(StorageBucket::DateTime));
        self::assertSame('eav_values_bool', $config->getValueTableName(StorageBucket::Boolean));
        self::assertSame('eav_values_text', $config->getValueTableName(StorageBucket::Text));
        self::assertSame('eav_values_json', $config->getValueTableName(StorageBucket::Json));
        self::assertSame('eav_values_blob', $config->getValueTableName(StorageBucket::Blob));

        self::assertSame('eav_flat_product', $config->getFlatTableName('product'));
        self::assertSame('eav_view_product', $config->getFlatViewName('product'));
    }

    public function testCustomPrefixAndOverrides(): void
    {
        $config = new TableConfig(
            prefix: 'custom_',
            flatPrefix: 'flat_',
            viewPrefix: 'view_',
        );

        self::assertSame('custom_entity_types', $config->getEntityTypesTable());
        self::assertSame('custom_attributes', $config->getAttributesTable());
        self::assertSame('custom_entity_type_attributes', $config->getEntityTypeAttributesTable());
        self::assertSame('custom_entities', $config->getEntitiesTable());
        self::assertSame('custom_values_string', $config->getValueTableName(StorageBucket::String));
        self::assertSame('flat_product', $config->getFlatTableName('product'));
        self::assertSame('view_product', $config->getFlatViewName('product'));
    }
}
