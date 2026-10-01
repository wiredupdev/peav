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

namespace WireUpDev\Peav\Repository;

use WireUpDev\Peav\Exception\EntityTypeNotFoundException;
use WireUpDev\Peav\Exception\InvalidAttributeValueException;
use WireUpDev\Peav\Model\EavEntity;

/**
 * Primary repository contract for creating, saving, batch-hydrating, and deleting EAV entities.
 */
interface EavRepositoryInterface
{
    /**
     * Instantiates an entity with preset default values applied and typed custom class if configured.
     *
     * @param string $entityTypeCode Entity type identifier
     * @param array<string, mixed> $attributes Initial attribute values
     */
    public function createEntity(string $entityTypeCode, array $attributes = []): EavEntity;

    /**
     * Persists a new or existing entity to storage within a transaction.
     *
     * @throws InvalidAttributeValueException If a required attribute is missing or invalid.
     * @throws EntityTypeNotFoundException If the entity type is not registered.
     */
    public function save(EavEntity $entity): void;

    /**
     * Persists multiple entities in a single batch transaction.
     *
     * @param list<EavEntity> $entities
     */
    public function saveMany(array $entities): void;

    /**
     * Finds a single entity by its identifier.
     */
    public function find(string $entityTypeCode, int|string $id): ?EavEntity;

    /**
     * Finds and hydrates multiple entities in a constant query count O(T).
     *
     * @param string $entityTypeCode
     * @param list<int|string> $ids
     * @return array<int|string, EavEntity>
     */
    public function findMany(string $entityTypeCode, array $ids): array;

    /**
     * Deletes an entity and its associated typed attribute values.
     */
    public function delete(EavEntity $entity): void;

    /**
     * Deletes an entity by its identifier.
     */
    public function deleteById(string $entityTypeCode, int|string $id): void;
}
