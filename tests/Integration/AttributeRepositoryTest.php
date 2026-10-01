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

namespace WireUpDev\Peav\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use WireUpDev\Peav\Event\AttributeDeletedEvent;
use WireUpDev\Peav\Event\AttributeSavedEvent;
use WireUpDev\Peav\Event\EntityTypePresetUpdatedEvent;
use WireUpDev\Peav\Exception\AttributeInUseException;
use WireUpDev\Peav\Factory\CacheFactory;
use WireUpDev\Peav\Factory\EventDispatcherFactory;
use WireUpDev\Peav\Factory\LoggerFactory;
use WireUpDev\Peav\Model\AttributeDefinition;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Model\PresetAttribute;
use WireUpDev\Peav\Repository\AttributeRepository;
use WireUpDev\Peav\Schema\SchemaBuilder;
use WireUpDev\Peav\Schema\SchemaSynchronizer;
use WireUpDev\Peav\Schema\TableConfig;
use WireUpDev\Peav\Type\AttributeType;
use WireUpDev\Peav\Type\TypeRegistry;

final class AttributeRepositoryTest extends TestCase
{
    public function testSaveAndLoadAttribute(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $tableConfig = new TableConfig();
        $schemaSynchronizer = new SchemaSynchronizer($connection, new SchemaBuilder($tableConfig), $tableConfig);
        $schemaSynchronizer->createSchema();

        $typeRegistry = new TypeRegistry();
        $cache = CacheFactory::create('test_attr');
        $dispatcher = EventDispatcherFactory::create();
        $logger = LoggerFactory::create('test_attr');

        $repo = new AttributeRepository(
            $connection,
            $typeRegistry,
            $tableConfig,
            $cache,
            $dispatcher,
            $logger,
        );

        $sku = new AttributeDefinition('sku', AttributeType::String, 'Stock Keeping Unit', 'SKU description');
        $repo->saveAttribute($sku);

        $loaded = $repo->getAttribute('sku');
        self::assertNotNull($loaded);
        self::assertSame('sku', $loaded->getCode());
        self::assertSame(AttributeType::String, $loaded->getType());
        self::assertSame('Stock Keeping Unit', $loaded->getName());
        self::assertSame('SKU description', $loaded->getDescription());

        // Cache hit test
        $cached = $repo->getAttribute('sku');
        self::assertNotNull($cached);
        self::assertSame('sku', $cached->getCode());

        $all = $repo->getAllAttributes();
        self::assertArrayHasKey('sku', $all);
    }

    public function testSaveAndLoadEntityTypeWithPresets(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $tableConfig = new TableConfig();
        $schemaSynchronizer = new SchemaSynchronizer($connection, new SchemaBuilder($tableConfig), $tableConfig);
        $schemaSynchronizer->createSchema();

        $typeRegistry = new TypeRegistry();
        $cache = CacheFactory::create('test_type');
        $dispatcher = EventDispatcherFactory::create();
        $logger = LoggerFactory::create('test_type');

        $repo = new AttributeRepository(
            $connection,
            $typeRegistry,
            $tableConfig,
            $cache,
            $dispatcher,
            $logger,
        );

        $sku = new AttributeDefinition('sku', AttributeType::String, 'SKU');
        $price = new AttributeDefinition('price', AttributeType::Decimal, 'Price');
        $repo->saveAttribute($sku);
        $repo->saveAttribute($price);

        $productType = new EntityTypeDefinition('product', 'Product Entity', 'Catalog item', presets: [
            new PresetAttribute($sku, isRequired: true, position: 1),
            new PresetAttribute($price, isRequired: false, defaultValue: '0.00', position: 2),
        ]);

        $repo->saveEntityType($productType);

        $loaded = $repo->getEntityType('product');
        self::assertNotNull($loaded);
        self::assertSame('product', $loaded->getCode());
        self::assertSame('Product Entity', $loaded->getName());
        self::assertTrue($loaded->hasPreset('sku'));
        self::assertTrue($loaded->hasPreset('price'));

        $skuPreset = $loaded->getPreset('sku');
        self::assertNotNull($skuPreset);
        self::assertTrue($skuPreset->isRequired());
        self::assertSame(1, $skuPreset->getPosition());

        $pricePreset = $loaded->getPreset('price');
        self::assertNotNull($pricePreset);
        self::assertFalse($pricePreset->isRequired());
        self::assertSame('0.00', $pricePreset->getDefaultValue());
    }

    public function testSafeAttributeDeletionProtection(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $tableConfig = new TableConfig();
        $schemaSynchronizer = new SchemaSynchronizer($connection, new SchemaBuilder($tableConfig), $tableConfig);
        $schemaSynchronizer->createSchema();

        $typeRegistry = new TypeRegistry();
        $cache = CacheFactory::create('test_del');
        $dispatcher = EventDispatcherFactory::create();
        $logger = LoggerFactory::create('test_del');

        $repo = new AttributeRepository(
            $connection,
            $typeRegistry,
            $tableConfig,
            $cache,
            $dispatcher,
            $logger,
        );

        $sku = new AttributeDefinition('sku', AttributeType::String, 'SKU');
        $repo->saveAttribute($sku);

        // Unused attribute can be deleted safely
        self::assertFalse($repo->isAttributeInUse('sku'));
        $repo->deleteAttribute('sku');
        self::assertNull($repo->getAttribute('sku'));

        // Re-create and attach to an entity record
        $repo->saveAttribute($sku);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $connection->insert('eav_entity_types', ['code' => 'product', 'name' => 'Product', 'created_at' => $now]);
        $typeId = (int) $connection->lastInsertId();
        $connection->insert('eav_entities', ['entity_type_id' => $typeId, 'created_at' => $now]);
        $entityId = (int) $connection->lastInsertId();
        $attrId = (int) $connection->fetchOne('SELECT id FROM eav_attributes WHERE code = ?', ['sku']);

        // Insert a value row in eav_values_string
        $connection->insert('eav_values_string', [
            'entity_id' => $entityId,
            'attribute_id' => $attrId,
            'value' => 'PROD-100',
        ]);

        // Attempting to delete when in use MUST throw AttributeInUseException
        self::assertTrue($repo->isAttributeInUse('sku'));
        $this->expectException(AttributeInUseException::class);
        $repo->deleteAttribute('sku');
    }
}
