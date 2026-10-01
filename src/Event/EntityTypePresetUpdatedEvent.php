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

namespace WireUpDev\Peav\Event;

use WireUpDev\Peav\Model\EntityTypeDefinition;

/**
 * Dispatched when preset attributes for an entity type are configured or updated.
 */
final readonly class EntityTypePresetUpdatedEvent
{
    public function __construct(
        private EntityTypeDefinition $entityType,
    ) {
    }

    public function getEntityType(): EntityTypeDefinition
    {
        return $this->entityType;
    }
}
