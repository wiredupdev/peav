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
use WireUpDev\Peav\Schema\SchemaSynchronizer;

/**
 * CLI command to dump platform-specific DDL creation scripts.
 */
#[AsCommand(name: 'peav:schema:dump-sql', description: 'Outputs pure DDL SQL migration statements for the connected platform.')]
class SchemaDumpCommand extends Command
{
    public function __construct(
        private readonly SchemaSynchronizer $synchronizer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queries = $this->synchronizer->getCreateSchemaSql();

        foreach ($queries as $query) {
            $output->writeln($query . ';');
        }

        return Command::SUCCESS;
    }
}
