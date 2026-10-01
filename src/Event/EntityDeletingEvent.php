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

use Psr\EventDispatcher\StoppableEventInterface;
use WireUpDev\Peav\Model\EavEntity;

/**
 * Dispatched before an entity is deleted from storage. Stoppable.
 */
final class EntityDeletingEvent implements StoppableEventInterface
{
    use StoppableEventTrait;

    public function __construct(
        private readonly EavEntity $entity,
    ) {
    }

    public function getEntity(): EavEntity
    {
        return $this->entity;
    }
}
