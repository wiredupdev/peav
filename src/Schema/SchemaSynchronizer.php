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

namespace WireUpDev\Peav\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WireUpDev\Peav\Event\SchemaMigratedEvent;
use WireUpDev\Peav\Event\SchemaMigratingEvent;
use WireUpDev\Peav\Factory\NullAdapters\NullEventDispatcher;

/**
 * Manages schema creation, diffing, updating, and dropping across DBAL platforms.
 */
class SchemaSynchronizer
{
    private readonly EventDispatcherInterface $eventDispatcher;
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly Connection $connection,
        private readonly SchemaBuilder $schemaBuilder = new SchemaBuilder(),
        private readonly TableConfig $tableConfig = new TableConfig(),
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->eventDispatcher = $eventDispatcher ?? new NullEventDispatcher();
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Creates all required EAV database tables.
     *
     * @param bool $dropFirst If true, drops existing tables before creation.
     */
    public function createSchema(bool $dropFirst = false): void
    {
        if ($dropFirst) {
            $this->dropSchema();
        }

        $queries = $this->getCreateSchemaSql();

        if (empty($queries)) {
            return;
        }

        $this->executeQueries($queries);
    }

    /**
     * Drops all EAV database tables safely.
     */
    public function dropSchema(): void
    {
        $schemaManager = $this->getSchemaManager();
        $allTables = $this->tableConfig->getAllStandardTableNames();

        $queries = [];
        // Drop in reverse order to respect foreign key constraints
        foreach (array_reverse($allTables) as $tableName) {
            if ($schemaManager->tableExists($tableName)) {
                $queries[] = sprintf('DROP TABLE %s', $tableName);
            }
        }

        if (!empty($queries)) {
            $this->executeQueries($queries);
        }
    }

    /**
     * Updates database schema according to computed diffs against desired schema.
     */
    public function updateSchema(bool $complete = false): void
    {
        $queries = $this->getMigrationSql($complete);

        if (!empty($queries)) {
            $this->executeQueries($queries);
        }
    }

    /**
     * Generates SQL DDL migration queries required to bring the database up to date.
     *
     * @return list<string>
     */
    public function getMigrationSql(bool $complete = false): array
    {
        $schemaManager = $this->getSchemaManager();
        $platform = $this->connection->getDatabasePlatform();
        $targetSchema = $this->schemaBuilder->buildSchema();

        $existingTables = $schemaManager->listTableNames();
        $standardTables = $this->tableConfig->getAllStandardTableNames();

        // If no Peav tables exist, generate full create SQL
        $hasAnyTable = false;
        foreach ($standardTables as $table) {
            if (in_array(strtolower($table), array_map('strtolower', $existingTables), true)) {
                $hasAnyTable = true;
                break;
            }
        }

        if (!$hasAnyTable) {
            return $targetSchema->toSql($platform);
        }

        $currentSchema = $schemaManager->introspectSchema();
        $comparator = $schemaManager->createComparator();
        $schemaDiff = $comparator->compareSchemas($currentSchema, $targetSchema);

        return $platform->getAlterSchemaSQL($schemaDiff);
    }

    /**
     * Generates full DDL SQL statements for creating all EAV tables on the current platform.
     *
     * @return list<string>
     */
    public function getCreateSchemaSql(): array
    {
        $platform = $this->connection->getDatabasePlatform();
        $targetSchema = $this->schemaBuilder->buildSchema();

        return $targetSchema->toSql($platform);
    }

    /**
     * Generates DDL SQL statements for dropping all EAV tables on the current platform.
     *
     * @return list<string>
     */
    public function getDropSchemaSql(): array
    {
        $schemaManager = $this->getSchemaManager();
        $allTables = $this->tableConfig->getAllStandardTableNames();

        $queries = [];
        foreach (array_reverse($allTables) as $tableName) {
            if ($schemaManager->tableExists($tableName)) {
                $queries[] = sprintf('DROP TABLE %s', $tableName);
            }
        }

        return $queries;
    }

    /**
     * Checks if all required EAV tables exist and are up to date.
     */
    public function isUpToDate(bool $complete = false): bool
    {
        $diffSql = $this->getMigrationSql($complete);

        return empty($diffSql);
    }

    /**
     * Returns a structured status array of all configured EAV tables.
     *
     * @return array<string, array{name: string, exists: bool, rowCount: int}>
     */
    public function getTableStatus(): array
    {
        $schemaManager = $this->getSchemaManager();
        $allTables = $this->tableConfig->getAllStandardTableNames();
        $status = [];

        foreach ($allTables as $tableName) {
            $exists = $schemaManager->tableExists($tableName);
            $rowCount = 0;

            if ($exists) {
                try {
                    $rowCount = (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $tableName));
                } catch (\Throwable) {
                    $rowCount = 0;
                }
            }

            $status[$tableName] = [
                'name' => $tableName,
                'exists' => $exists,
                'rowCount' => $rowCount,
            ];
        }

        return $status;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function getSchemaBuilder(): SchemaBuilder
    {
        return $this->schemaBuilder;
    }

    public function getTableConfig(): TableConfig
    {
        return $this->tableConfig;
    }

    /**
     * @param list<string> $queries
     */
    private function executeQueries(array $queries): void
    {
        $this->logger->info(sprintf('Executing schema migration with %d DDL statements.', count($queries)));
        $this->eventDispatcher->dispatch(new SchemaMigratingEvent($queries));

        foreach ($queries as $query) {
            $this->logger->debug(sprintf('Executing DDL: %s', $query));
            $this->connection->executeStatement($query);
        }

        $this->eventDispatcher->dispatch(new SchemaMigratedEvent($queries));
        $this->logger->info('Schema migration executed successfully.');
    }

    /**
     * @return AbstractSchemaManager<\Doctrine\DBAL\Platforms\AbstractPlatform>
     */
    private function getSchemaManager(): AbstractSchemaManager
    {
        return $this->connection->createSchemaManager();
    }
}
