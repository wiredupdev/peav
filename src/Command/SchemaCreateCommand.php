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
 * CLI command to create all EAV schema tables.
 */
#[AsCommand(name: 'peav:schema:create', description: 'Creates all EAV schema tables in the database.')]
class SchemaCreateCommand extends Command
{
    public function __construct(
        private readonly SchemaSynchronizer $synchronizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dump-sql', null, InputOption::VALUE_NONE, 'Dumps the generated SQL statements without executing them.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulates schema creation without executing DDL.')
            ->addOption('drop-first', null, InputOption::VALUE_NONE, 'Drops existing EAV tables before creating new ones.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dumpSql = (bool) $input->getOption('dump-sql');
        $dryRun = (bool) $input->getOption('dry-run');
        $dropFirst = (bool) $input->getOption('drop-first');

        $queries = $dropFirst
            ? array_merge($this->synchronizer->getDropSchemaSql(), $this->synchronizer->getCreateSchemaSql())
            : $this->synchronizer->getCreateSchemaSql();

        if (empty($queries)) {
            $io->info('No schema DDL statements to execute.');

            return Command::SUCCESS;
        }

        if ($dumpSql) {
            foreach ($queries as $query) {
                $output->writeln($query . ';');
            }

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->note(sprintf('Dry-run: %d DDL statements would be executed.', count($queries)));
            foreach ($queries as $query) {
                $output->writeln('<comment>' . $query . ';</comment>');
            }

            return Command::SUCCESS;
        }

        $io->section('Creating Peav EAV Schema');
        $this->synchronizer->createSchema($dropFirst);
        $io->success('EAV schema created successfully.');

        return Command::SUCCESS;
    }
}
