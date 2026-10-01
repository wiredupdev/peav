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
 * Contract for synchronizing flattened entities across physical tables, views, and non-SQL sinks.
 */
interface FlatIndexerInterface
{
    /**
     * Indexes a single entity across all active flat strategies.
     */
    public function indexEntity(EavEntity $entity, EntityTypeDefinition $entityType): void;

    /**
     * Removes an entity from all active flat strategies.
     */
    public function removeEntity(int|string $entityId, EntityTypeDefinition $entityType): void;

    /**
     * Performs a batch re-indexing of entities from normalized EAV storage to flat projections.
     *
     * @param string|null $entityTypeCode Specific entity type to re-index, or null for all
     * @param int $batchSize Chunk size for batch processing
     * @return int Total number of entities re-indexed
     */
    public function reindexAll(?string $entityTypeCode = null, int $batchSize = 500): int;
}
