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

namespace WireUpDev\Peav\Flat;

use WireUpDev\Peav\Model\EavEntity;
use WireUpDev\Peav\Model\EntityTypeDefinition;

/**
 * Contract for flat projection storage backends (physical tables, SQL views, non-SQL stores).
 */
interface FlatStorageStrategyInterface
{
    /**
     * Returns the unique name identifying this strategy.
     */
    public function getStrategyName(): string;

    /**
     * Synchronizes schema/structure for the specified entity type.
     */
    public function syncSchema(EntityTypeDefinition $entityType): void;

    /**
     * Drops schema/structure for the specified entity type.
     */
    public function dropSchema(EntityTypeDefinition $entityType): void;

    /**
     * Checks if the flat storage structure exists and is up-to-date.
     */
    public function isSynchronized(EntityTypeDefinition $entityType): bool;

    /**
     * Indexes a single entity into the flat projection.
     */
    public function indexEntity(EavEntity $entity, EntityTypeDefinition $entityType): void;

    /**
     * Removes an entity from the flat projection.
     */
    public function removeEntity(int|string $entityId, EntityTypeDefinition $entityType): void;

    /**
     * Indexes a batch of entities in bulk for high performance.
     *
     * @param list<EavEntity> $entities
     */
    public function batchIndex(array $entities, EntityTypeDefinition $entityType): void;
}
