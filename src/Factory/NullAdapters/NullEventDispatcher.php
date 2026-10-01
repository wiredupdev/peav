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

namespace WireUpDev\Peav\Factory\NullAdapters;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Fallback no-op PSR-14 event dispatcher.
 */
final readonly class NullEventDispatcher implements EventDispatcherInterface
{
    /**
     * @template T of object
     * @param T $event
     * @return T
     */
    public function dispatch(object $event): object
    {
        return $event;
    }
}
