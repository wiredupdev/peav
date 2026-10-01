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

/**
 * Dispatched when batch re-indexing of flat tables/views completes.
 */
final readonly class FlatTableReindexedEvent
{
    public function __construct(
        private ?string $entityType = null,
        private int $totalIndexed = 0,
    ) {
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function getTotalIndexed(): int
    {
        return $this->totalIndexed;
    }
}
