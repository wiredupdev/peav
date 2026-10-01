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

use WireUpDev\Peav\Exception\AttributeInUseException;
use WireUpDev\Peav\Exception\AttributeNotFoundException;
use WireUpDev\Peav\Exception\EntityTypeNotFoundException;
use WireUpDev\Peav\Model\AttributeDefinition;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Model\PresetAttribute;

/**
 * Contract for managing global reusable attribute definitions, entity types, and preset associations.
 */
interface AttributeRepositoryInterface
{
    /**
     * Persists or updates a global attribute definition.
     */
    public function saveAttribute(AttributeDefinition $attribute): void;

    /**
     * Retrieves an attribute definition by code.
     */
    public function getAttribute(string $code): ?AttributeDefinition;

    /**
     * Retrieves multiple attribute definitions by their codes.
     *
     * @param list<string> $codes
     * @return array<string, AttributeDefinition>
     */
    public function getAttributes(array $codes): array;

    /**
     * Returns all registered attribute definitions.
     *
     * @return array<string, AttributeDefinition>
     */
    public function getAllAttributes(): array;

    /**
     * Checks if an attribute is currently in use by any entity across all storage buckets.
     */
    public function isAttributeInUse(string $code): bool;

    /**
     * Safely deletes an unused attribute definition.
     *
     * @throws AttributeInUseException If the attribute is currently referenced or populated by any entity.
     * @throws AttributeNotFoundException If the attribute does not exist.
     */
    public function deleteAttribute(string $code): void;

    /**
     * Persists or updates an entity type definition and its attached preset attributes.
     */
    public function saveEntityType(EntityTypeDefinition $entityType): void;

    /**
     * Retrieves an entity type definition with all attached preset attributes by code.
     */
    public function getEntityType(string $code): ?EntityTypeDefinition;

    /**
     * Returns all configured entity type definitions.
     *
     * @return array<string, EntityTypeDefinition>
     */
    public function getAllEntityTypes(): array;

    /**
     * Deletes an entity type definition.
     *
     * @throws EntityTypeNotFoundException If the entity type does not exist.
     */
    public function deleteEntityType(string $code): void;

    /**
     * Attaches or updates a preset attribute on an entity type.
     */
    public function attachPreset(string $entityTypeCode, PresetAttribute $preset): void;

    /**
     * Detaches a preset attribute from an entity type.
     */
    public function detachPreset(string $entityTypeCode, string $attributeCode): void;
}
