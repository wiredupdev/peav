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
 * Interface contract for normalizing EAV entities into flat key-value arrays and JSON documents.
 */
interface FlatDocumentSerializerInterface
{
    /**
     * Converts an entity into an associative flat document array.
     *
     * @return array<string, mixed>
     */
    public function toArray(EavEntity $entity, EntityTypeDefinition $entityType): array;

    /**
     * Converts an entity into a JSON string document.
     */
    public function toJson(EavEntity $entity, EntityTypeDefinition $entityType): string;
}
