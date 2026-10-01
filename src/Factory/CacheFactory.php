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

use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * Factory for creating default PSR-16 Symfony Cache instances.
 */
final readonly class CacheFactory
{
    /**
     * Creates a default Symfony Psr16Cache wrapping an in-memory ArrayAdapter.
     *
     * @param string $namespace Cache namespace
     * @param int $defaultLifetime Default lifetime in seconds
     */
    public static function create(string $namespace = 'peav', int $defaultLifetime = 0): CacheInterface
    {
        $adapter = new ArrayAdapter($defaultLifetime);

        return new Psr16Cache($adapter);
    }
}
