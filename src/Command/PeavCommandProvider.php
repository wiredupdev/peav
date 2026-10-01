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

namespace WireUpDev\Peav\Command;

use Symfony\Component\Console\Command\Command;
use WireUpDev\Peav\Flat\FlatSynchronizer;
use WireUpDev\Peav\Schema\SchemaSynchronizer;

/**
 * Helper provider providing all Peav CLI commands for single-line framework registration.
 */
final readonly class PeavCommandProvider
{
    /**
     * @return list<Command>
     */
    public static function getCommands(
        SchemaSynchronizer $synchronizer,
        ?FlatSynchronizer $flatSynchronizer = null,
        ?object $flatIndexer = null,
    ): array {
        return [
            new SchemaCreateCommand($synchronizer),
            new SchemaUpdateCommand($synchronizer),
            new SchemaStatusCommand($synchronizer),
            new SchemaDropCommand($synchronizer),
            new SchemaDumpCommand($synchronizer),
            new FlatTableSyncCommand($flatSynchronizer),
            new FlatTableReindexCommand($flatIndexer),
        ];
    }
}
