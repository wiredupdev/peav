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

namespace WireUpDev\Peav\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use WireUpDev\Peav\EavManager;
use WireUpDev\Peav\Factory\NullAdapters\NullCache;
use WireUpDev\Peav\Factory\NullAdapters\NullEventDispatcher;
use WireUpDev\Peav\Model\AttributeDefinition;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Model\PresetAttribute;
use WireUpDev\Peav\Type\AttributeType;

class InterchangeabilityTest extends TestCase
{
    private \Doctrine\DBAL\Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
    }

    public function testWorksSeamlesslyWithCustomPsrImplementations(): void
    {
        // Custom PSR-3 Logger
        $loggedMessages = [];
        $customLogger = $this->createMock(LoggerInterface::class);
        $customLogger->method('info')->willReturnCallback(function (string|\Stringable $message) use (&$loggedMessages) {
            $loggedMessages[] = (string) $message;
        });
        $customLogger->method('debug')->willReturnCallback(function (string|\Stringable $message) use (&$loggedMessages) {
            $loggedMessages[] = (string) $message;
        });

        // Custom PSR-14 EventDispatcher
        $dispatchedEvents = [];
        $customDispatcher = $this->createMock(EventDispatcherInterface::class);
        $customDispatcher->method('dispatch')->willReturnCallback(function (object $event) use (&$dispatchedEvents) {
            $dispatchedEvents[] = get_class($event);

            return $event;
        });

        // Custom PSR-16 Cache
        /** @var array<string, mixed> $cacheStorage */
        $cacheStorage = [];
        $customCache = $this->createMock(CacheInterface::class);
        $customCache->method('get')->willReturnCallback(function (string $key, mixed $default = null) use (&$cacheStorage) {
            return array_key_exists($key, $cacheStorage) ? $cacheStorage[$key] : $default;
        });
        $customCache->method('set')->willReturnCallback(function (string $key, mixed $value) use (&$cacheStorage) {
            $cacheStorage[$key] = $value;

            return true;
        });
        $customCache->method('delete')->willReturnCallback(function (string $key) use (&$cacheStorage) {
            unset($cacheStorage[$key]);

            return true;
        });

        $manager = EavManager::create(
            connection: $this->connection,
            cache: $customCache,
            eventDispatcher: $customDispatcher,
            logger: $customLogger,
        );

        $manager->schema()->createSchema();

        $attr = new AttributeDefinition('code_num', AttributeType::Integer, 'Code Number');
        $manager->attributes()->saveAttribute($attr);

        $type = new EntityTypeDefinition('device', 'Device', presets: [
            'code_num' => new PresetAttribute($attr, true, 100, 1),
        ]);
        $manager->attributes()->saveEntityType($type);

        $entity = $manager->createEntity('device', ['code_num' => 500]);
        $manager->save($entity);

        $id = $entity->getId();
        $this->assertNotNull($id);
        $found = $manager->find('device', $id);
        $this->assertNotNull($found);
        $this->assertSame(500, $found->get('code_num'));

        // Assert custom PSR implementations received calls
        $this->assertNotEmpty($loggedMessages);
        $this->assertNotEmpty($dispatchedEvents);
        $this->assertNotEmpty($cacheStorage);
    }

    public function testWorksWithNullAdapters(): void
    {
        $nullLogger = new \Psr\Log\NullLogger();
        $nullDispatcher = new NullEventDispatcher();
        $nullCache = new NullCache();

        $manager = EavManager::create(
            connection: $this->connection,
            cache: $nullCache,
            eventDispatcher: $nullDispatcher,
            logger: $nullLogger,
        );

        $manager->schema()->createSchema();

        $attr = new AttributeDefinition('tag', AttributeType::String, 'Tag');
        $manager->attributes()->saveAttribute($attr);

        $type = new EntityTypeDefinition('post', 'Post', presets: [
            'tag' => new PresetAttribute($attr, false, 'news', 1),
        ]);
        $manager->attributes()->saveEntityType($type);

        $entity = $manager->createEntity('post', ['tag' => 'tech']);
        $manager->save($entity);

        $this->assertNotNull($entity->getId());
        $loaded = $manager->find('post', $entity->getId());
        $this->assertNotNull($loaded);
        $this->assertSame('tech', $loaded->get('tag'));
    }
}
