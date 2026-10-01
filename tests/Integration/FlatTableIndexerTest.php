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
use WireUpDev\Peav\Event\FlatTableReindexedEvent;
use WireUpDev\Peav\Event\FlatTableReindexingEvent;
use WireUpDev\Peav\Factory\CacheFactory;
use WireUpDev\Peav\Factory\EventDispatcherFactory;
use WireUpDev\Peav\Factory\LoggerFactory;
use WireUpDev\Peav\Flat\FlatConfig;
use WireUpDev\Peav\Flat\FlatDocumentSerializer;
use WireUpDev\Peav\Flat\FlatIndexer;
use WireUpDev\Peav\Flat\FlatStorageRegistry;
use WireUpDev\Peav\Flat\FlatStorageStrategyInterface;
use WireUpDev\Peav\Flat\FlatTableBuilder;
use WireUpDev\Peav\Flat\FlatViewBuilder;
use WireUpDev\Peav\Flat\Strategy\PhysicalFlatTableStrategy;
use WireUpDev\Peav\Flat\Strategy\ViewFlatTableStrategy;
use WireUpDev\Peav\Model\AttributeDefinition;
use WireUpDev\Peav\Model\EavEntity;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Model\PresetAttribute;
use WireUpDev\Peav\Repository\AttributeRepository;
use WireUpDev\Peav\Schema\SchemaBuilder;
use WireUpDev\Peav\Schema\SchemaSynchronizer;
use WireUpDev\Peav\Schema\TableConfig;
use WireUpDev\Peav\Type\AttributeType;
use WireUpDev\Peav\Type\TypeRegistry;

final class FlatTableIndexerTest extends TestCase
{
    public function testIndexAndRemoveEntityMultiSink(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $tableConfig = new TableConfig();
        $schemaSynchronizer = new SchemaSynchronizer($connection, new SchemaBuilder($tableConfig), $tableConfig);
        $schemaSynchronizer->createSchema();

        $typeRegistry = new TypeRegistry();
        $cache = CacheFactory::create('test_indexer');
        $dispatcher = EventDispatcherFactory::create();
        $logger = LoggerFactory::create('test_indexer');

        $attrRepo = new AttributeRepository($connection, $typeRegistry, $tableConfig, $cache, $dispatcher, $logger);

        $sku = new AttributeDefinition('sku', AttributeType::String, 'SKU');
        $price = new AttributeDefinition('price', AttributeType::Decimal, 'Price');
        $attrRepo->saveAttribute($sku);
        $attrRepo->saveAttribute($price);

        $productType = new EntityTypeDefinition('product', 'Product Entity', presets: [
            new PresetAttribute($sku, isRequired: true),
            new PresetAttribute($price, defaultValue: '0.00'),
        ]);
        $attrRepo->saveEntityType($productType);

        $flatConfig = new FlatConfig();
        $flatTableBuilder = new FlatTableBuilder($tableConfig, $flatConfig);
        $physicalStrategy = new PhysicalFlatTableStrategy($connection, $flatTableBuilder, $tableConfig, $flatConfig, $logger);
        $physicalStrategy->syncSchema($productType);

        // Custom document store sink (e.g. mock MongoDB/Elasticsearch)
        $documentStore = [];
        $serializer = new FlatDocumentSerializer();
        $customSink = new class($documentStore, $serializer) implements FlatStorageStrategyInterface {
            /**
             * @param array<string, array<string, mixed>> $store
             */
            public function __construct(
                public array &$store,
                private readonly FlatDocumentSerializer $serializer,
            ) {
            }

            public function getStrategyName(): string
            {
                return 'mock_document_store';
            }

            public function syncSchema(EntityTypeDefinition $entityType): void
            {
            }

            public function dropSchema(EntityTypeDefinition $entityType): void
            {
                $this->store = [];
            }

            public function isSynchronized(EntityTypeDefinition $entityType): bool
            {
                return true;
            }

            public function indexEntity(EavEntity $entity, EntityTypeDefinition $entityType): void
            {
                $this->store[(string) $entity->getId()] = $this->serializer->toArray($entity, $entityType);
            }

            public function removeEntity(int|string $entityId, EntityTypeDefinition $entityType): void
            {
                unset($this->store[(string) $entityId]);
            }

            public function batchIndex(array $entities, EntityTypeDefinition $entityType): void
            {
                foreach ($entities as $e) {
                    $this->indexEntity($e, $entityType);
                }
            }
        };

        $registry = new FlatStorageRegistry($flatConfig);
        $registry->registerStrategy($physicalStrategy);
        $registry->registerStrategy($customSink);
        $registry->mapEntityType('product', ['physical', 'mock_document_store']);

        $indexer = new FlatIndexer(
            $connection,
            $registry,
            $attrRepo,
            $tableConfig,
            $flatConfig,
            $dispatcher,
            $logger,
        );

        $entity = new EavEntity('product', 10, ['sku' => 'LAPTOP-10', 'price' => 1299.99]);
        $indexer->indexEntity($entity, $productType);

        // Check physical flat table row
        $flatRow = $connection->fetchAssociative('SELECT * FROM eav_flat_product WHERE entity_id = 10');
        self::assertIsArray($flatRow);
        self::assertSame('LAPTOP-10', $flatRow['sku']);
        self::assertEquals(1299.99, (float) $flatRow['price']);

        // Check custom document store
        self::assertArrayHasKey('10', $documentStore);
        self::assertSame('LAPTOP-10', $documentStore['10']['sku']);

        // Remove entity
        $indexer->removeEntity(10, $productType);
        $flatRowAfter = $connection->fetchAssociative('SELECT * FROM eav_flat_product WHERE entity_id = 10');
        self::assertFalse($flatRowAfter);
        self::assertArrayNotHasKey('10', $documentStore);
    }
}
