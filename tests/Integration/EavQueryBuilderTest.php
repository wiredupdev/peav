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
use WireUpDev\Peav\Factory\CacheFactory;
use WireUpDev\Peav\Factory\LoggerFactory;
use WireUpDev\Peav\Flat\FlatConfig;
use WireUpDev\Peav\Flat\FlatIndexer;
use WireUpDev\Peav\Flat\FlatStorageRegistry;
use WireUpDev\Peav\Flat\FlatStrategyMode;
use WireUpDev\Peav\Flat\FlatSynchronizer;
use WireUpDev\Peav\Flat\Strategy\PhysicalFlatTableStrategy;
use WireUpDev\Peav\Flat\Strategy\ViewFlatTableStrategy;
use WireUpDev\Peav\Model\AttributeDefinition;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Model\PresetAttribute;
use WireUpDev\Peav\Query\EavQueryBuilder;
use WireUpDev\Peav\Repository\AttributeRepository;
use WireUpDev\Peav\Repository\EavRepository;
use WireUpDev\Peav\Schema\SchemaSynchronizer;
use WireUpDev\Peav\Schema\TableConfig;
use WireUpDev\Peav\Type\AttributeType;
use WireUpDev\Peav\Type\TypeRegistry;

class EavQueryBuilderTest extends TestCase
{
    private \Doctrine\DBAL\Connection $connection;
    private TableConfig $tableConfig;
    private FlatConfig $flatConfig;
    private TypeRegistry $typeRegistry;
    private AttributeRepository $attributeRepository;
    private FlatStorageRegistry $flatRegistry;
    private FlatIndexer $flatIndexer;
    private EavRepository $eavRepository;
    private SchemaSynchronizer $schemaSynchronizer;
    private FlatSynchronizer $flatSynchronizer;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->tableConfig = new TableConfig();
        $this->flatConfig = new FlatConfig(defaultMode: FlatStrategyMode::Physical);
        $this->typeRegistry = new TypeRegistry();

        $cache = CacheFactory::create();
        $logger = LoggerFactory::create(stream: null);

        $this->attributeRepository = new AttributeRepository(
            $this->connection,
            $this->typeRegistry,
            $this->tableConfig,
            $cache,
            null,
            $logger,
        );

        $this->flatRegistry = new FlatStorageRegistry($this->flatConfig);
        $flatTableBuilder = new \WireUpDev\Peav\Flat\FlatTableBuilder($this->tableConfig, $this->flatConfig);
        $flatViewBuilder = new \WireUpDev\Peav\Flat\FlatViewBuilder($this->tableConfig, $this->flatConfig);
        $physicalStrategy = new PhysicalFlatTableStrategy($this->connection, $flatTableBuilder, $this->tableConfig, $this->flatConfig);
        $viewStrategy = new ViewFlatTableStrategy($this->connection, $flatViewBuilder, $this->tableConfig, $this->flatConfig);
        $this->flatRegistry->registerStrategy($physicalStrategy);
        $this->flatRegistry->registerStrategy($viewStrategy);

        $this->flatSynchronizer = new FlatSynchronizer($this->flatRegistry, $this->flatConfig);
        $this->flatIndexer = new FlatIndexer($this->connection, $this->flatRegistry, $this->attributeRepository, $this->tableConfig, $this->flatConfig);

        $this->eavRepository = new EavRepository(
            $this->connection,
            $this->attributeRepository,
            $this->typeRegistry,
            $this->flatIndexer,
            $this->tableConfig,
            null,
            $logger,
        );

        $schemaBuilder = new \WireUpDev\Peav\Schema\SchemaBuilder($this->tableConfig);
        $this->schemaSynchronizer = new SchemaSynchronizer($this->connection, $schemaBuilder, $this->tableConfig);
        $this->schemaSynchronizer->createSchema();

        $this->seedTestData();
    }

    private function seedTestData(): void
    {
        // 1. Create attributes
        $sku = new AttributeDefinition('sku', AttributeType::String, 'Product SKU');
        $price = new AttributeDefinition('price', AttributeType::Decimal, 'Product Price');
        $stock = new AttributeDefinition('stock', AttributeType::Integer, 'Stock Quantity');
        $isActive = new AttributeDefinition('is_active', AttributeType::Boolean, 'Active Status');
        $tags = new AttributeDefinition('tags', AttributeType::Json, 'Product Tags');
        $releasedAt = new AttributeDefinition('released_at', AttributeType::DateTimeImmutable, 'Release Date');
        $nonPreset = new AttributeDefinition('extra_notes', AttributeType::Text, 'Extra Notes');

        $this->attributeRepository->saveAttribute($sku);
        $this->attributeRepository->saveAttribute($price);
        $this->attributeRepository->saveAttribute($stock);
        $this->attributeRepository->saveAttribute($isActive);
        $this->attributeRepository->saveAttribute($tags);
        $this->attributeRepository->saveAttribute($releasedAt);
        $this->attributeRepository->saveAttribute($nonPreset);

        // 2. Create product entity type with presets
        $presets = [
            'sku' => new PresetAttribute($sku, true, null, 1),
            'price' => new PresetAttribute($price, true, 0.0, 2),
            'stock' => new PresetAttribute($stock, false, 0, 3),
            'is_active' => new PresetAttribute($isActive, false, true, 4),
            'tags' => new PresetAttribute($tags, false, ['general'], 5),
            'released_at' => new PresetAttribute($releasedAt, false, null, 6),
        ];

        $productType = new EntityTypeDefinition(
            code: 'product',
            name: 'Product Entity',
            description: 'Product Entity',
            presets: $presets,
        );
        $this->attributeRepository->saveEntityType($productType);

        // Sync flat physical table
        $this->flatSynchronizer->syncEntityType($productType);

        // 3. Insert test entities
        $p1 = $this->eavRepository->createEntity('product', [
            'sku' => 'LAPTOP-PRO',
            'price' => 1299.99,
            'stock' => 15,
            'is_active' => true,
            'tags' => ['tech', 'laptop'],
            'released_at' => new \DateTimeImmutable('2024-01-15 10:00:00'),
            'extra_notes' => 'Flagship product notes',
        ]);
        $this->eavRepository->save($p1);

        $p2 = $this->eavRepository->createEntity('product', [
            'sku' => 'MOUSE-WL',
            'price' => 49.99,
            'stock' => 150,
            'is_active' => true,
            'tags' => ['accessories', 'wireless'],
            'released_at' => new \DateTimeImmutable('2024-02-01 12:00:00'),
        ]);
        $this->eavRepository->save($p2);

        $p3 = $this->eavRepository->createEntity('product', [
            'sku' => 'KEYBOARD-MECH',
            'price' => 120.00,
            'stock' => 0,
            'is_active' => false,
            'tags' => ['accessories', 'gaming'],
            'released_at' => new \DateTimeImmutable('2024-03-20 14:00:00'),
            'extra_notes' => 'Out of stock backlog',
        ]);
        $this->eavRepository->save($p3);

        $p4 = $this->eavRepository->createEntity('product', [
            'sku' => 'MONITOR-4K',
            'price' => 499.00,
            'stock' => 25,
            'is_active' => true,
            'tags' => ['displays', '4k'],
            'released_at' => new \DateTimeImmutable('2024-05-10 09:30:00'),
        ]);
        $this->eavRepository->save($p4);
    }

    private function createQueryBuilder(): EavQueryBuilder
    {
        return new EavQueryBuilder(
            connection: $this->connection,
            entityTypeCode: 'product',
            attributeRepository: $this->attributeRepository,
            typeRegistry: $this->typeRegistry,
            tableConfig: $this->tableConfig,
            flatConfig: $this->flatConfig,
            flatStorageRegistry: $this->flatRegistry,
            eavRepository: $this->eavRepository,
        );
    }

    public function testQueryAllEntitiesUsingFlatRouting(): void
    {
        $qb = $this->createQueryBuilder();
        $this->assertSame('flat_table', $qb->getRoutingDecision());

        $entities = $qb->getEntities();
        $this->assertCount(4, $entities);
        $this->assertSame(4, $qb->count());
    }

    public function testFilterByAttributeEquality(): void
    {
        $qb = $this->createQueryBuilder();
        $qb->whereAttribute('sku', '=', 'MOUSE-WL');

        $entities = $qb->getEntities();
        $this->assertCount(1, $entities);
        $this->assertSame('MOUSE-WL', $entities[0]->get('sku'));
        $this->assertSame(49.99, (float) $entities[0]->get('price'));
    }

    public function testFilterByComparisonOperators(): void
    {
        $qb = $this->createQueryBuilder();
        $qb->whereAttribute('price', '>', 100.00)
            ->whereAttribute('is_active', '=', true);

        $entities = $qb->getEntities();
        $this->assertCount(2, $entities); // LAPTOP-PRO (1299.99), MONITOR-4K (499.00)

        $skus = array_map(fn($e) => $e->get('sku'), $entities);
        $this->assertContains('LAPTOP-PRO', $skus);
        $this->assertContains('MONITOR-4K', $skus);
    }

    public function testFilterBetween(): void
    {
        $qb = $this->createQueryBuilder();
        $qb->whereAttributeBetween('price', 50.00, 500.00);

        $entities = $qb->getEntities();
        $this->assertCount(2, $entities); // KEYBOARD-MECH (120), MONITOR-4K (499)
    }

    public function testFilterInAndNotIn(): void
    {
        $qb = $this->createQueryBuilder();
        $qb->whereAttributeIn('sku', ['MOUSE-WL', 'MONITOR-4K']);

        $entities = $qb->getEntities();
        $this->assertCount(2, $entities);

        $qb2 = $this->createQueryBuilder();
        $qb2->whereAttributeNotIn('sku', ['MOUSE-WL', 'MONITOR-4K']);

        $entities2 = $qb2->getEntities();
        $this->assertCount(2, $entities2);
    }

    public function testFilterLike(): void
    {
        $qb = $this->createQueryBuilder();
        $qb->whereAttributeLike('sku', '%PRO%');

        $entities = $qb->getEntities();
        $this->assertCount(1, $entities);
        $this->assertSame('LAPTOP-PRO', $entities[0]->get('sku'));
    }

    public function testSortingAndPagination(): void
    {
        $qb = $this->createQueryBuilder();
        $qb->orderByAttribute('price', 'DESC')
            ->limit(2, 0);

        $entities = $qb->getEntities();
        $this->assertCount(2, $entities);
        $this->assertSame('LAPTOP-PRO', $entities[0]->get('sku')); // 1299.99
        $this->assertSame('MONITOR-4K', $entities[1]->get('sku'));  // 499.00

        // Page 2
        $qbPage2 = $this->createQueryBuilder();
        $qbPage2->orderByAttribute('price', 'DESC')
            ->paginate(page: 2, perPage: 2);

        $page2Entities = $qbPage2->getEntities();
        $this->assertCount(2, $page2Entities);
        $this->assertSame('KEYBOARD-MECH', $page2Entities[0]->get('sku')); // 120.00
        $this->assertSame('MOUSE-WL', $page2Entities[1]->get('sku'));      // 49.99
    }

    public function testFirstMethod(): void
    {
        $qb = $this->createQueryBuilder();
        $qb->whereAttribute('sku', '=', 'MONITOR-4K');

        $entity = $qb->first();
        $this->assertNotNull($entity);
        $this->assertSame('MONITOR-4K', $entity->get('sku'));

        $qbNotFound = $this->createQueryBuilder();
        $qbNotFound->whereAttribute('sku', '=', 'DOES-NOT-EXIST');
        $this->assertNull($qbNotFound->first());
    }

    public function testGetIds(): void
    {
        $qb = $this->createQueryBuilder();
        $qb->whereAttribute('is_active', '=', true);

        $ids = $qb->getIds();
        $this->assertCount(3, $ids);
        $this->assertContainsOnly('integer', $ids);
    }

    public function testFallbackToNormalizedEavWhenNonPresetAttributeQueried(): void
    {
        $qb = $this->createQueryBuilder();
        // extra_notes is a global attribute, but NOT a preset on product
        $qb->whereAttribute('extra_notes', 'LIKE', '%Flagship%');

        $this->assertSame('normalized_eav', $qb->getRoutingDecision());

        $entities = $qb->getEntities();
        $this->assertCount(1, $entities);
        $this->assertSame('LAPTOP-PRO', $entities[0]->get('sku'));
        $this->assertSame('Flagship product notes', $entities[0]->get('extra_notes'));
    }

    public function testForceNormalizedEavRouting(): void
    {
        $qb = $this->createQueryBuilder();
        $qb->withFlatRouting(false)
            ->whereAttribute('price', '<', 100.00);

        $this->assertSame('normalized_eav', $qb->getRoutingDecision());

        $entities = $qb->getEntities();
        $this->assertCount(1, $entities);
        $this->assertSame('MOUSE-WL', $entities[0]->get('sku'));
    }

    public function testQueryWithSqlViewFlatRouting(): void
    {
        // Reconfigure flat mode to SQL View
        $viewFlatConfig = new FlatConfig(
            defaultMode: FlatStrategyMode::View,
            entityTypeModes: ['product' => FlatStrategyMode::View],
        );
        $productType = $this->attributeRepository->getEntityType('product');
        $this->assertNotNull($productType);

        $viewStrategy = new ViewFlatTableStrategy(
            $this->connection,
            new \WireUpDev\Peav\Flat\FlatViewBuilder($this->tableConfig, $viewFlatConfig),
            $this->tableConfig,
            $viewFlatConfig,
        );
        $viewStrategy->syncSchema($productType);

        $qb = new EavQueryBuilder(
            connection: $this->connection,
            entityTypeCode: 'product',
            attributeRepository: $this->attributeRepository,
            typeRegistry: $this->typeRegistry,
            tableConfig: $this->tableConfig,
            flatConfig: $viewFlatConfig,
            flatStorageRegistry: $this->flatRegistry,
            eavRepository: $this->eavRepository,
        );

        $this->assertSame('flat_view', $qb->getRoutingDecision());

        $qb->whereAttribute('stock', '>', 20);
        $entities = $qb->getEntities();

        $this->assertCount(2, $entities); // MOUSE-WL (150), MONITOR-4K (25)
    }

    public function testQueryByNativeEntityProperties(): void
    {
        $qb = $this->createQueryBuilder();
        $qb->whereId(1);

        $entity = $qb->first();
        $this->assertNotNull($entity);
        $this->assertSame(1, $entity->getId());
    }

    public function testOrWhereCriteria(): void
    {
        $qb = $this->createQueryBuilder();
        $qb->whereAttribute('sku', '=', 'LAPTOP-PRO')
            ->orWhereAttribute('sku', '=', 'MOUSE-WL');

        $entities = $qb->getEntities();
        $this->assertCount(2, $entities);
    }
}
