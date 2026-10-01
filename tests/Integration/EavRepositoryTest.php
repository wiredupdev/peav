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
use WireUpDev\Peav\Caster\BooleanTypeCaster;
use WireUpDev\Peav\Caster\DateTimeTypeCaster;
use WireUpDev\Peav\Caster\DecimalTypeCaster;
use WireUpDev\Peav\Caster\IntegerTypeCaster;
use WireUpDev\Peav\Caster\JsonTypeCaster;
use WireUpDev\Peav\Caster\StringTypeCaster;
use WireUpDev\Peav\Event\EntityCreatedEvent;
use WireUpDev\Peav\Event\EntityCreatingEvent;
use WireUpDev\Peav\Event\EntityDeletedEvent;
use WireUpDev\Peav\Event\EntityDeletingEvent;
use WireUpDev\Peav\Event\EntityLoadedEvent;
use WireUpDev\Peav\Event\EntityUpdatedEvent;
use WireUpDev\Peav\Event\EntityUpdatingEvent;
use WireUpDev\Peav\Exception\InvalidAttributeValueException;
use WireUpDev\Peav\Factory\CacheFactory;
use WireUpDev\Peav\Factory\EventDispatcherFactory;
use WireUpDev\Peav\Factory\LoggerFactory;
use WireUpDev\Peav\Flat\FlatConfig;
use WireUpDev\Peav\Flat\FlatIndexer;
use WireUpDev\Peav\Flat\FlatStorageRegistry;
use WireUpDev\Peav\Flat\FlatTableBuilder;
use WireUpDev\Peav\Flat\Strategy\PhysicalFlatTableStrategy;
use WireUpDev\Peav\Model\AttributeDefinition;
use WireUpDev\Peav\Model\EavEntity;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Model\PresetAttribute;
use WireUpDev\Peav\Repository\AttributeRepository;
use WireUpDev\Peav\Repository\EavRepository;
use WireUpDev\Peav\Schema\SchemaBuilder;
use WireUpDev\Peav\Schema\SchemaSynchronizer;
use WireUpDev\Peav\Schema\TableConfig;
use WireUpDev\Peav\Type\AttributeType;
use WireUpDev\Peav\Type\TypeRegistry;

final class TestCustomProduct extends EavEntity
{
    public function getSku(): ?string
    {
        return $this->get('sku');
    }

    public function getPrice(): float
    {
        return (float) $this->get('price', 0.0);
    }
}

final class EavRepositoryTest extends TestCase
{
    public function testEntityLifecycleAndPersistence(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $tableConfig = new TableConfig();
        $schemaSynchronizer = new SchemaSynchronizer($connection, new SchemaBuilder($tableConfig), $tableConfig);
        $schemaSynchronizer->createSchema();

        $typeRegistry = new TypeRegistry();
        $cache = CacheFactory::create('test_eav');
        $dispatcher = EventDispatcherFactory::create();
        $logger = LoggerFactory::create('test_eav');

        $eventsObserved = [];
        if ($dispatcher instanceof \Symfony\Component\EventDispatcher\EventDispatcher) {
            $dispatcher->addListener(EntityCreatingEvent::class, function () use (&$eventsObserved): void { $eventsObserved[] = 'creating'; });
            $dispatcher->addListener(EntityCreatedEvent::class, function () use (&$eventsObserved): void { $eventsObserved[] = 'created'; });
            $dispatcher->addListener(EntityUpdatingEvent::class, function () use (&$eventsObserved): void { $eventsObserved[] = 'updating'; });
            $dispatcher->addListener(EntityUpdatedEvent::class, function () use (&$eventsObserved): void { $eventsObserved[] = 'updated'; });
            $dispatcher->addListener(EntityDeletingEvent::class, function () use (&$eventsObserved): void { $eventsObserved[] = 'deleting'; });
            $dispatcher->addListener(EntityDeletedEvent::class, function () use (&$eventsObserved): void { $eventsObserved[] = 'deleted'; });
            $dispatcher->addListener(EntityLoadedEvent::class, function () use (&$eventsObserved): void { $eventsObserved[] = 'loaded'; });
        }

        $attrRepo = new AttributeRepository($connection, $typeRegistry, $tableConfig, $cache, $dispatcher, $logger);

        // Register attributes
        $sku = new AttributeDefinition('sku', AttributeType::String, 'SKU');
        $price = new AttributeDefinition('price', AttributeType::Decimal, 'Price');
        $stock = new AttributeDefinition('stock', AttributeType::Integer, 'Stock Quantity');
        $isActive = new AttributeDefinition('is_active', AttributeType::Boolean, 'Active');
        $tags = new AttributeDefinition('tags', AttributeType::Json, 'Tags');
        $releasedAt = new AttributeDefinition('released_at', AttributeType::DateTimeImmutable, 'Release Date');

        $attrRepo->saveAttribute($sku);
        $attrRepo->saveAttribute($price);
        $attrRepo->saveAttribute($stock);
        $attrRepo->saveAttribute($isActive);
        $attrRepo->saveAttribute($tags);
        $attrRepo->saveAttribute($releasedAt);

        $productType = new EntityTypeDefinition(
            'product',
            'Product Entity',
            customClass: TestCustomProduct::class,
            presets: [
                new PresetAttribute($sku, isRequired: true),
                new PresetAttribute($price, defaultValue: '0.00'),
                new PresetAttribute($stock, defaultValue: 0),
                new PresetAttribute($isActive, defaultValue: true),
            ],
        );
        $attrRepo->saveEntityType($productType);

        $flatConfig = new FlatConfig();
        $flatRegistry = new FlatStorageRegistry($flatConfig);
        $flatTableBuilder = new FlatTableBuilder($tableConfig, $flatConfig);
        $physicalStrategy = new PhysicalFlatTableStrategy($connection, $flatTableBuilder, $tableConfig, $flatConfig, $logger);
        $physicalStrategy->syncSchema($productType);
        $flatRegistry->registerStrategy($physicalStrategy);

        $flatIndexer = new FlatIndexer($connection, $flatRegistry, $attrRepo, $tableConfig, $flatConfig, $dispatcher, $logger);

        $eavRepo = new EavRepository(
            $connection,
            $attrRepo,
            $typeRegistry,
            $flatIndexer,
            $tableConfig,
            $dispatcher,
            $logger,
        );

        // 1. Create entity using createEntity (assert custom class and preset defaults applied)
        $entity = $eavRepo->createEntity('product', [
            'sku' => 'PHONE-99',
            'price' => '699.99',
            'tags' => ['5g', 'oled'],
            'released_at' => new \DateTimeImmutable('2026-09-30 00:00:00'),
        ]);

        self::assertInstanceOf(TestCustomProduct::class, $entity);
        self::assertSame('PHONE-99', $entity->getSku());
        self::assertSame(699.99, $entity->getPrice());
        self::assertSame(0, $entity->get('stock')); // Preset default applied
        self::assertTrue($entity->get('is_active')); // Preset default applied

        // 2. Persist entity
        $eavRepo->save($entity);
        self::assertNotNull($entity->getId());
        self::assertContains('creating', $eventsObserved);
        self::assertContains('created', $eventsObserved);

        // Check that flat table was also synchronized
        $flatRow = $connection->fetchAssociative('SELECT * FROM eav_flat_product WHERE entity_id = ?', [$entity->getId()]);
        self::assertIsArray($flatRow);
        self::assertSame('PHONE-99', $flatRow['sku']);

        // 3. Load entity
        $entityId = $entity->getId();
        self::assertNotNull($entityId);
        $loaded = $eavRepo->find('product', $entityId);
        self::assertNotNull($loaded);
        self::assertInstanceOf(TestCustomProduct::class, $loaded);
        self::assertSame('PHONE-99', $loaded->get('sku'));
        self::assertSame('699.99', $loaded->get('price'));
        self::assertSame(0, $loaded->get('stock'));
        self::assertTrue($loaded->get('is_active'));
        self::assertSame(['5g', 'oled'], $loaded->get('tags'));
        self::assertInstanceOf(\DateTimeImmutable::class, $loaded->get('released_at'));

        // 4. Update entity
        $loaded->set('stock', 25);
        $loaded->remove('tags');
        $eavRepo->save($loaded);

        self::assertContains('updating', $eventsObserved);
        self::assertContains('updated', $eventsObserved);

        $reloaded = $eavRepo->find('product', $entityId);
        self::assertNotNull($reloaded);
        self::assertSame(25, $reloaded->get('stock'));
        self::assertNull($reloaded->get('tags'));

        // 5. Batch loading
        $entity2 = $eavRepo->createEntity('product', ['sku' => 'TABLET-01', 'price' => 499.00]);
        $eavRepo->save($entity2);
        $entity2Id = $entity2->getId();
        self::assertNotNull($entity2Id);

        $batch = $eavRepo->findMany('product', [$entityId, $entity2Id]);
        self::assertCount(2, $batch);
        self::assertSame('PHONE-99', $batch[$entityId]->get('sku'));
        self::assertSame('TABLET-01', $batch[$entity2Id]->get('sku'));

        // 6. Delete entity
        $eavRepo->delete($entity);
        self::assertContains('deleting', $eventsObserved);
        self::assertContains('deleted', $eventsObserved);

        self::assertNull($eavRepo->find('product', $entityId));

        // Flat table row removed
        $flatRowDeleted = $connection->fetchAssociative('SELECT * FROM eav_flat_product WHERE entity_id = ?', [$entity->getId()]);
        self::assertFalse($flatRowDeleted);
    }

    public function testRequiredPresetValidation(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $tableConfig = new TableConfig();
        $schemaSynchronizer = new SchemaSynchronizer($connection, new SchemaBuilder($tableConfig), $tableConfig);
        $schemaSynchronizer->createSchema();

        $typeRegistry = new TypeRegistry();
        $attrRepo = new AttributeRepository($connection, $typeRegistry, $tableConfig);

        $sku = new AttributeDefinition('sku', AttributeType::String, 'SKU');
        $attrRepo->saveAttribute($sku);

        $productType = new EntityTypeDefinition('product', 'Product Entity', presets: [
            new PresetAttribute($sku, isRequired: true),
        ]);
        $attrRepo->saveEntityType($productType);

        $eavRepo = new EavRepository($connection, $attrRepo, $typeRegistry);

        $invalidEntity = new EavEntity('product'); // Missing required 'sku'

        $this->expectException(InvalidAttributeValueException::class);
        $eavRepo->save($invalidEntity);
    }
}
