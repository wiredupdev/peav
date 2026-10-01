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

use WireUpDev\Peav\Model\EavEntity;

/**
 * Dispatched after an entity has been updated in storage.
 */
final readonly class EntityUpdatedEvent
{
    public function __construct(
        private EavEntity $entity,
    ) {
    }

    public function getEntity(): EavEntity
    {
        return $this->entity;
    }
}
