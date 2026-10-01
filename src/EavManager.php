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

namespace WireUpDev\Peav;

use Doctrine\DBAL\Connection;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Console\Command\Command;
use WireUpDev\Peav\Command\PeavCommandProvider;
use WireUpDev\Peav\Factory\CacheFactory;
use WireUpDev\Peav\Factory\EventDispatcherFactory;
use WireUpDev\Peav\Factory\LoggerFactory;
use WireUpDev\Peav\Flat\FlatConfig;
use WireUpDev\Peav\Flat\FlatIndexer;
use WireUpDev\Peav\Flat\FlatIndexerInterface;
use WireUpDev\Peav\Flat\FlatStorageRegistry;
use WireUpDev\Peav\Flat\FlatSynchronizer;
use WireUpDev\Peav\Flat\FlatTableBuilder;
use WireUpDev\Peav\Flat\FlatViewBuilder;
use WireUpDev\Peav\Flat\Strategy\PhysicalFlatTableStrategy;
use WireUpDev\Peav\Flat\Strategy\ViewFlatTableStrategy;
use WireUpDev\Peav\Model\EavEntity;
use WireUpDev\Peav\Query\EavQueryBuilder;
use WireUpDev\Peav\Repository\AttributeRepository;
use WireUpDev\Peav\Repository\AttributeRepositoryInterface;
use WireUpDev\Peav\Repository\EavRepository;
use WireUpDev\Peav\Repository\EavRepositoryInterface;
use WireUpDev\Peav\Schema\SchemaBuilder;
use WireUpDev\Peav\Schema\SchemaSynchronizer;
use WireUpDev\Peav\Schema\TableConfig;
use WireUpDev\Peav\Type\TypeRegistry;

/**
 * Unified entry point facade orchestrating all Peav EAV features with zero-friction defaults and PSR interchangeability.
 */
class EavManager implements EavManagerInterface
{
    private readonly EavRepositoryInterface $eavRepository;
    private readonly AttributeRepositoryInterface $attributeRepository;
    private readonly TypeRegistry $typeRegistry;
    private readonly SchemaSynchronizer $schemaSynchronizer;
    private readonly FlatStorageRegistry $flatStorageRegistry;
    private readonly FlatSynchronizer $flatSynchronizer;
    private readonly FlatIndexerInterface $flatIndexer;
    private readonly TableConfig $tableConfig;
    private readonly FlatConfig $flatConfig;
    private readonly LoggerInterface $logger;
    private readonly EventDispatcherInterface $eventDispatcher;
    private readonly CacheInterface $cache;

    public function __construct(
        private readonly Connection $connection,
        ?AttributeRepositoryInterface $attributeRepository = null,
        ?EavRepositoryInterface $eavRepository = null,
        ?TypeRegistry $typeRegistry = null,
        ?SchemaSynchronizer $schemaSynchronizer = null,
        ?FlatStorageRegistry $flatStorageRegistry = null,
        ?FlatSynchronizer $flatSynchronizer = null,
        ?FlatIndexerInterface $flatIndexer = null,
        ?TableConfig $tableConfig = null,
        ?FlatConfig $flatConfig = null,
        ?CacheInterface $cache = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->tableConfig = $tableConfig ?? new TableConfig();
        $this->flatConfig = $flatConfig ?? new FlatConfig();
        $this->typeRegistry = $typeRegistry ?? new TypeRegistry();

        $this->cache = $cache ?? CacheFactory::create();
        $this->eventDispatcher = $eventDispatcher ?? EventDispatcherFactory::create();
        $this->logger = $logger ?? LoggerFactory::create();

        $this->attributeRepository = $attributeRepository ?? new AttributeRepository(
            $this->connection,
            $this->typeRegistry,
            $this->tableConfig,
            $this->cache,
            $this->eventDispatcher,
            $this->logger,
        );

        $this->flatStorageRegistry = $flatStorageRegistry ?? new FlatStorageRegistry($this->flatConfig);

        if (!$this->flatStorageRegistry->hasStrategy('physical')) {
            $flatTableBuilder = new FlatTableBuilder($this->tableConfig, $this->flatConfig);
            $this->flatStorageRegistry->registerStrategy(
                new PhysicalFlatTableStrategy($this->connection, $flatTableBuilder, $this->tableConfig, $this->flatConfig, $this->logger)
            );
        }

        if (!$this->flatStorageRegistry->hasStrategy('view')) {
            $flatViewBuilder = new FlatViewBuilder($this->tableConfig, $this->flatConfig);
            $this->flatStorageRegistry->registerStrategy(
                new ViewFlatTableStrategy($this->connection, $flatViewBuilder, $this->tableConfig, $this->flatConfig, $this->logger)
            );
        }

        $this->flatSynchronizer = $flatSynchronizer ?? new FlatSynchronizer(
            $this->flatStorageRegistry,
            $this->flatConfig,
            $this->logger,
        );

        $this->flatIndexer = $flatIndexer ?? new FlatIndexer(
            $this->connection,
            $this->flatStorageRegistry,
            $this->attributeRepository,
            $this->tableConfig,
            $this->flatConfig,
            $this->eventDispatcher,
            $this->logger,
        );

        $this->eavRepository = $eavRepository ?? new EavRepository(
            $this->connection,
            $this->attributeRepository,
            $this->typeRegistry,
            $this->flatIndexer,
            $this->tableConfig,
            $this->eventDispatcher,
            $this->logger,
        );

        $this->schemaSynchronizer = $schemaSynchronizer ?? new SchemaSynchronizer(
            $this->connection,
            new SchemaBuilder($this->tableConfig),
            $this->tableConfig,
            $this->eventDispatcher,
            $this->logger,
        );
    }

    /**
     * Static factory helper initializing default Monolog, Symfony EventDispatcher, and Symfony Cache instances.
     */
    public static function create(
        Connection $connection,
        ?CacheInterface $cache = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
        ?TableConfig $tableConfig = null,
        ?FlatConfig $flatConfig = null,
    ): self {
        return new self(
            connection: $connection,
            tableConfig: $tableConfig,
            flatConfig: $flatConfig,
            cache: $cache,
            eventDispatcher: $eventDispatcher,
            logger: $logger,
        );
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function repository(): EavRepositoryInterface
    {
        return $this->eavRepository;
    }

    public function attributes(): AttributeRepositoryInterface
    {
        return $this->attributeRepository;
    }

    public function types(): TypeRegistry
    {
        return $this->typeRegistry;
    }

    public function schema(): SchemaSynchronizer
    {
        return $this->schemaSynchronizer;
    }

    public function flat(): FlatSynchronizer
    {
        return $this->flatSynchronizer;
    }

    public function indexer(): FlatIndexerInterface
    {
        return $this->flatIndexer;
    }

    public function flatRegistry(): FlatStorageRegistry
    {
        return $this->flatStorageRegistry;
    }

    public function logger(): LoggerInterface
    {
        return $this->logger;
    }

    public function events(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function cache(): CacheInterface
    {
        return $this->cache;
    }

    public function getTableConfig(): TableConfig
    {
        return $this->tableConfig;
    }

    public function getFlatConfig(): FlatConfig
    {
        return $this->flatConfig;
    }

    public function createQueryBuilder(string $entityTypeCode): EavQueryBuilder
    {
        return new EavQueryBuilder(
            connection: $this->connection,
            entityTypeCode: $entityTypeCode,
            attributeRepository: $this->attributeRepository,
            typeRegistry: $this->typeRegistry,
            tableConfig: $this->tableConfig,
            flatConfig: $this->flatConfig,
            flatStorageRegistry: $this->flatStorageRegistry,
            eavRepository: $this->eavRepository,
            logger: $this->logger,
        );
    }

    public function createEntity(string $entityTypeCode, array $attributes = []): EavEntity
    {
        return $this->eavRepository->createEntity($entityTypeCode, $attributes);
    }

    public function find(string $entityTypeCode, int|string $id): ?EavEntity
    {
        return $this->eavRepository->find($entityTypeCode, $id);
    }

    public function findMany(string $entityTypeCode, array $ids): array
    {
        return $this->eavRepository->findMany($entityTypeCode, $ids);
    }

    public function save(EavEntity $entity): void
    {
        $this->eavRepository->save($entity);
    }

    public function saveMany(array $entities): void
    {
        $this->eavRepository->saveMany($entities);
    }

    public function delete(EavEntity $entity): void
    {
        $this->eavRepository->delete($entity);
    }

    public function deleteById(string $entityTypeCode, int|string $id): void
    {
        $this->eavRepository->deleteById($entityTypeCode, $id);
    }

    public function commands(): array
    {
        return PeavCommandProvider::getCommands(
            $this->schemaSynchronizer,
            $this->flatSynchronizer,
            $this->flatIndexer,
        );
    }
}
