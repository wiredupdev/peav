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

namespace WireUpDev\Peav\Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use WireUpDev\Peav\Factory\CacheFactory;
use WireUpDev\Peav\Factory\EventDispatcherFactory;
use WireUpDev\Peav\Factory\LoggerFactory;
use WireUpDev\Peav\Factory\NullAdapters\NullCache;
use WireUpDev\Peav\Factory\NullAdapters\NullEventDispatcher;

final class FactoryTest extends TestCase
{
    public function testLoggerFactoryCreatesPsrLogger(): void
    {
        $logger = LoggerFactory::create('peav_test');
        self::assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testEventDispatcherFactoryCreatesPsrEventDispatcher(): void
    {
        $dispatcher = EventDispatcherFactory::create();
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
    }

    public function testCacheFactoryCreatesPsrCache(): void
    {
        $cache = CacheFactory::create('peav_test');
        self::assertInstanceOf(CacheInterface::class, $cache);
    }

    public function testNullEventDispatcher(): void
    {
        $dispatcher = new NullEventDispatcher();
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $event = new \stdClass();
        $returned = $dispatcher->dispatch($event);
        self::assertSame($event, $returned);
    }

    public function testNullCache(): void
    {
        $cache = new NullCache();
        self::assertInstanceOf(CacheInterface::class, $cache);

        self::assertNull($cache->get('key'));
        self::assertSame('default', $cache->get('key', 'default'));
        self::assertTrue($cache->set('key', 'value'));
        self::assertTrue($cache->delete('key'));
        self::assertTrue($cache->clear());
        self::assertSame(['k1' => 'def', 'k2' => 'def'], $cache->getMultiple(['k1', 'k2'], 'def'));
        self::assertTrue($cache->setMultiple(['k1' => 'v1']));
        self::assertTrue($cache->deleteMultiple(['k1', 'k2']));
        self::assertFalse($cache->has('key'));
    }
}
