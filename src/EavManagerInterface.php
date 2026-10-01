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
use WireUpDev\Peav\Caster\CasterRegistry;
use WireUpDev\Peav\Flat\FlatConfig;
use WireUpDev\Peav\Flat\FlatIndexerInterface;
use WireUpDev\Peav\Flat\FlatStorageRegistry;
use WireUpDev\Peav\Flat\FlatSynchronizer;
use WireUpDev\Peav\Model\EavEntity;
use WireUpDev\Peav\Query\EavQueryBuilder;
use WireUpDev\Peav\Repository\AttributeRepositoryInterface;
use WireUpDev\Peav\Repository\EavRepositoryInterface;
use WireUpDev\Peav\Schema\SchemaSynchronizer;
use WireUpDev\Peav\Schema\TableConfig;
use WireUpDev\Peav\Type\TypeRegistry;

/**
 * Unified entry point and orchestrator interface for all Peav EAV subsystems.
 */
interface EavManagerInterface
{
    /**
     * Returns the underlying Doctrine DBAL connection.
     */
    public function getConnection(): Connection;

    /**
     * Returns the entity persistence repository.
     */
    public function repository(): EavRepositoryInterface;

    /**
     * Returns the attribute and entity type metadata repository.
     */
    public function attributes(): AttributeRepositoryInterface;

    /**
     * Returns the type registry for attribute types.
     */
    public function types(): TypeRegistry;

    /**
     * Returns the caster registry for managing type casters.
     */
    public function casters(): CasterRegistry;

    /**
     * Returns the database schema synchronizer.
     */
    public function schema(): SchemaSynchronizer;

    /**
     * Returns the flat storage projection synchronizer.
     */
    public function flat(): FlatSynchronizer;

    /**
     * Returns the flat indexing engine.
     */
    public function indexer(): FlatIndexerInterface;

    /**
     * Returns the flat storage strategy registry.
     */
    public function flatRegistry(): FlatStorageRegistry;

    /**
     * Returns the PSR-3 logger instance.
     */
    public function logger(): LoggerInterface;

    /**
     * Returns the PSR-14 event dispatcher instance.
     */
    public function events(): EventDispatcherInterface;

    /**
     * Returns the PSR-16 cache instance.
     */
    public function cache(): CacheInterface;

    /**
     * Returns table configuration.
     */
    public function getTableConfig(): TableConfig;

    /**
     * Returns flat projection configuration.
     */
    public function getFlatConfig(): FlatConfig;

    /**
     * Creates a new fluent EavQueryBuilder for the specified entity type.
     */
    public function createQueryBuilder(string $entityTypeCode): EavQueryBuilder;

    /**
     * Instantiates a new EavEntity for the given entity type, applying preset default values.
     *
     * @param array<string, mixed> $attributes
     */
    public function createEntity(string $entityTypeCode, array $attributes = []): EavEntity;

    /**
     * Finds a single entity by type and ID.
     */
    public function find(string $entityTypeCode, int|string $id): ?EavEntity;

    /**
     * Finds multiple entities by type and IDs in a batch.
     *
     * @param list<int|string> $ids
     * @return array<int|string, EavEntity>
     */
    public function findMany(string $entityTypeCode, array $ids): array;

    /**
     * Persists an entity and its dynamic attributes.
     */
    public function save(EavEntity $entity): void;

    /**
     * Persists multiple entities in a transaction.
     *
     * @param list<EavEntity> $entities
     */
    public function saveMany(array $entities): void;

    /**
     * Deletes an entity and its dynamic attribute values.
     */
    public function delete(EavEntity $entity): void;

    /**
     * Deletes an entity by its type and ID.
     */
    public function deleteById(string $entityTypeCode, int|string $id): void;

    /**
     * Returns all configured Symfony Console commands for Peav.
     *
     * @return list<Command>
     */
    public function commands(): array;
}
