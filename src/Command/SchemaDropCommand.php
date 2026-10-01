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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WireUpDev\Peav\Schema\SchemaSynchronizer;

/**
 * CLI command to safely drop all EAV tables.
 */
#[AsCommand(name: 'peav:schema:drop', description: 'Drops all EAV tables from the database.')]
class SchemaDropCommand extends Command
{
    public function __construct(
        private readonly SchemaSynchronizer $synchronizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forces dropping tables without interactive confirmation.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        if (!$force) {
            $confirmed = $io->confirm('Are you sure you want to drop all EAV database tables and erase all data?', false);
            if (!$confirmed) {
                $io->warning('Schema drop canceled.');

                return Command::SUCCESS;
            }
        }

        $io->section('Dropping Peav EAV Schema');
        $this->synchronizer->dropSchema();
        $io->success('EAV schema dropped successfully.');

        return Command::SUCCESS;
    }
}
