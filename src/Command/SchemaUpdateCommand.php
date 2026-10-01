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
 * CLI command to compare and update the EAV schema with database state.
 */
#[AsCommand(name: 'peav:schema:update', description: 'Updates EAV database tables to match configured definitions.')]
class SchemaUpdateCommand extends Command
{
    public function __construct(
        private readonly SchemaSynchronizer $synchronizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forces execution of the generated SQL migration statements.')
            ->addOption('dump-sql', null, InputOption::VALUE_NONE, 'Dumps the generated migration SQL without executing.')
            ->addOption('complete', null, InputOption::VALUE_NONE, 'Includes dropping foreign keys/indexes not in the schema definition.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $dumpSql = (bool) $input->getOption('dump-sql');
        $complete = (bool) $input->getOption('complete');

        $queries = $this->synchronizer->getMigrationSql($complete);

        if (empty($queries)) {
            $io->success('Database schema is already up to date with EAV definitions.');

            return Command::SUCCESS;
        }

        if ($dumpSql) {
            foreach ($queries as $query) {
                $output->writeln($query . ';');
            }

            return Command::SUCCESS;
        }

        if (!$force) {
            $io->warning(sprintf('Found %d schema differences.', count($queries)));
            foreach ($queries as $query) {
                $output->writeln('<comment>' . $query . ';</comment>');
            }
            $io->note('Run with --force to execute these DDL statements.');

            return Command::FAILURE;
        }

        $io->section('Updating EAV Database Schema');
        $this->synchronizer->updateSchema($complete);
        $io->success(sprintf('EAV schema updated successfully (%d statements executed).', count($queries)));

        return Command::SUCCESS;
    }
}
