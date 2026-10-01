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
use WireUpDev\Peav\Event\SchemaMigratedEvent;
use WireUpDev\Peav\Event\SchemaMigratingEvent;
use WireUpDev\Peav\Factory\EventDispatcherFactory;
use WireUpDev\Peav\Factory\LoggerFactory;
use WireUpDev\Peav\Schema\SchemaBuilder;
use WireUpDev\Peav\Schema\SchemaSynchronizer;
use WireUpDev\Peav\Schema\TableConfig;

final class SchemaSynchronizerTest extends TestCase
{
    public function testSchemaCreateAndDropLifecycle(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $tableConfig = new TableConfig();
        $schemaBuilder = new SchemaBuilder($tableConfig);

        $eventsDispatched = [];
        $dispatcher = EventDispatcherFactory::create();
        if ($dispatcher instanceof \Symfony\Component\EventDispatcher\EventDispatcher) {
            $dispatcher->addListener(SchemaMigratingEvent::class, function (SchemaMigratingEvent $e) use (&$eventsDispatched): void {
                $eventsDispatched['migrating'] = $e->getQueries();
            });
            $dispatcher->addListener(SchemaMigratedEvent::class, function (SchemaMigratedEvent $e) use (&$eventsDispatched): void {
                $eventsDispatched['migrated'] = $e->getQueries();
            });
        }

        $logger = LoggerFactory::create('peav_test');
        $synchronizer = new SchemaSynchronizer($connection, $schemaBuilder, $tableConfig, $dispatcher, $logger);

        // Before creation, status shows missing tables
        $statusBefore = $synchronizer->getTableStatus();
        self::assertArrayHasKey('eav_entities', $statusBefore);
        self::assertFalse($statusBefore['eav_entities']['exists']);

        // Create schema
        $synchronizer->createSchema();

        $statusAfter = $synchronizer->getTableStatus();
        self::assertTrue($statusAfter['eav_entities']['exists']);
        self::assertTrue($statusAfter['eav_values_string']['exists']);
        self::assertTrue($statusAfter['eav_attributes']['exists']);

        self::assertArrayHasKey('migrating', $eventsDispatched);
        self::assertArrayHasKey('migrated', $eventsDispatched);

        // Verify isUpToDate
        self::assertTrue($synchronizer->isUpToDate());

        // Drop schema
        $synchronizer->dropSchema();
        $statusDropped = $synchronizer->getTableStatus();
        self::assertFalse($statusDropped['eav_entities']['exists']);
    }

    public function testDumpSql(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $tableConfig = new TableConfig();
        $schemaBuilder = new SchemaBuilder($tableConfig);
        $synchronizer = new SchemaSynchronizer($connection, $schemaBuilder, $tableConfig);

        $sqlStatements = $synchronizer->getCreateSchemaSql();
        self::assertNotEmpty($sqlStatements);

        $joinedSql = implode(";\n", $sqlStatements);
        self::assertStringContainsString('eav_entity_types', $joinedSql);
        self::assertStringContainsString('eav_attributes', $joinedSql);
        self::assertStringContainsString('eav_values_string', $joinedSql);
    }
}
