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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WireUpDev\Peav\Schema\SchemaSynchronizer;

/**
 * CLI command to inspect and display EAV database table status.
 */
#[AsCommand(name: 'peav:schema:status', description: 'Displays table presence and row counts for all EAV tables.')]
class SchemaStatusCommand extends Command
{
    public function __construct(
        private readonly SchemaSynchronizer $synchronizer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $statusList = $this->synchronizer->getTableStatus();

        $rows = [];
        $totalTables = count($statusList);
        $existingTables = 0;

        foreach ($statusList as $tableName => $status) {
            if ($status['exists']) {
                $existingTables++;
            }

            $rows[] = [
                $tableName,
                $status['exists'] ? '<info>Exists</info>' : '<error>Missing</error>',
                $status['exists'] ? (string) $status['rowCount'] : '-',
            ];
        }

        $io->title('Peav EAV Table Status');
        $io->table(['Table Name', 'Status', 'Record Count'], $rows);

        if ($existingTables === $totalTables) {
            $io->success(sprintf('All %d EAV tables exist.', $totalTables));
        } else {
            $io->warning(sprintf('%d of %d EAV tables are missing. Run peav:schema:create or peav:schema:update to initialize.', $totalTables - $existingTables, $totalTables));
        }

        return Command::SUCCESS;
    }
}
