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
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Migration engine for comparing database schema state and applying non-destructive updates.
 */
class SchemaMigrator
{
    private readonly SchemaSynchronizer $synchronizer;

    public function __construct(
        Connection $connection,
        SchemaBuilder $schemaBuilder = new SchemaBuilder(),
        TableConfig $tableConfig = new TableConfig(),
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->synchronizer = new SchemaSynchronizer(
            $connection,
            $schemaBuilder,
            $tableConfig,
            $eventDispatcher,
            $logger,
        );
    }

    /**
     * Executes non-destructive migration.
     */
    public function migrate(bool $complete = false): void
    {
        $this->synchronizer->updateSchema($complete);
    }

    /**
     * Computes the list of SQL statements needed to bring schema up-to-date.
     *
     * @return list<string>
     */
    public function getMigrationSql(bool $complete = false): array
    {
        return $this->synchronizer->getMigrationSql($complete);
    }

    public function isUpToDate(bool $complete = false): bool
    {
        return $this->synchronizer->isUpToDate($complete);
    }

    public function getSynchronizer(): SchemaSynchronizer
    {
        return $this->synchronizer;
    }
}
