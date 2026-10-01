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

namespace WireUpDev\Peav\Tests\Integration\Command;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use WireUpDev\Peav\Command\PeavCommandProvider;
use WireUpDev\Peav\Command\SchemaCreateCommand;
use WireUpDev\Peav\Command\SchemaDropCommand;
use WireUpDev\Peav\Command\SchemaDumpCommand;
use WireUpDev\Peav\Command\SchemaStatusCommand;
use WireUpDev\Peav\Command\SchemaUpdateCommand;
use WireUpDev\Peav\Schema\SchemaBuilder;
use WireUpDev\Peav\Schema\SchemaSynchronizer;
use WireUpDev\Peav\Schema\TableConfig;

final class SchemaCommandsTest extends TestCase
{
    public function testSchemaCreateCommand(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $tableConfig = new TableConfig();
        $schemaBuilder = new SchemaBuilder($tableConfig);
        $synchronizer = new SchemaSynchronizer($connection, $schemaBuilder, $tableConfig);

        $command = new SchemaCreateCommand($synchronizer);
        $tester = new CommandTester($command);

        // Test with --dump-sql
        $tester->execute(['--dump-sql' => true]);
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('CREATE TABLE eav_entity_types', $tester->getDisplay());

        // Test real execution
        $tester->execute([]);
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('EAV schema created successfully.', $tester->getDisplay());
        self::assertTrue($synchronizer->isUpToDate());
    }

    public function testSchemaStatusCommand(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $tableConfig = new TableConfig();
        $schemaBuilder = new SchemaBuilder($tableConfig);
        $synchronizer = new SchemaSynchronizer($connection, $schemaBuilder, $tableConfig);

        $command = new SchemaStatusCommand($synchronizer);
        $tester = new CommandTester($command);

        $tester->execute([]);
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('eav_entity_types', $tester->getDisplay());
        self::assertStringContainsString('Missing', $tester->getDisplay());

        $synchronizer->createSchema();
        $tester->execute([]);
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Exists', $tester->getDisplay());
    }

    public function testSchemaUpdateCommand(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $tableConfig = new TableConfig();
        $schemaBuilder = new SchemaBuilder($tableConfig);
        $synchronizer = new SchemaSynchronizer($connection, $schemaBuilder, $tableConfig);

        $command = new SchemaUpdateCommand($synchronizer);
        $tester = new CommandTester($command);

        // When not yet created, update --force creates schema
        $tester->execute(['--force' => true]);
        self::assertSame(0, $tester->getStatusCode());
        self::assertTrue($synchronizer->isUpToDate());
    }

    public function testSchemaDropCommand(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $tableConfig = new TableConfig();
        $schemaBuilder = new SchemaBuilder($tableConfig);
        $synchronizer = new SchemaSynchronizer($connection, $schemaBuilder, $tableConfig);
        $synchronizer->createSchema();

        $command = new SchemaDropCommand($synchronizer);
        $tester = new CommandTester($command);

        $tester->execute(['--force' => true]);
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('EAV schema dropped successfully.', $tester->getDisplay());
    }

    public function testSchemaDumpCommand(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $tableConfig = new TableConfig();
        $schemaBuilder = new SchemaBuilder($tableConfig);
        $synchronizer = new SchemaSynchronizer($connection, $schemaBuilder, $tableConfig);

        $command = new SchemaDumpCommand($synchronizer);
        $tester = new CommandTester($command);

        $tester->execute([]);
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('CREATE TABLE eav_entity_types', $tester->getDisplay());
    }

    public function testPeavCommandProvider(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $tableConfig = new TableConfig();
        $schemaBuilder = new SchemaBuilder($tableConfig);
        $synchronizer = new SchemaSynchronizer($connection, $schemaBuilder, $tableConfig);

        $commands = PeavCommandProvider::getCommands($synchronizer);
        self::assertCount(7, $commands);
    }
}
