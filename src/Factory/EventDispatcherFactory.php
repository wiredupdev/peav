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

namespace WireUpDev\Peav\Factory;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Factory for creating default PSR-14 Symfony EventDispatcher instances.
 */
final readonly class EventDispatcherFactory
{
    /**
     * Creates a default Symfony EventDispatcher instance implementing PSR-14.
     */
    public static function create(): EventDispatcherInterface
    {
        return new EventDispatcher();
    }
}
