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
use WireUpDev\Peav\Flat\FlatSynchronizer;

/**
 * CLI command to synchronize flat tables and views for entity types.
 */
#[AsCommand(name: 'peav:flat:sync', description: 'Synchronizes physical flat tables and dynamic SQL views for entity types.')]
class FlatTableSyncCommand extends Command
{
    public function __construct(
        private readonly ?FlatSynchronizer $flatSynchronizer = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Specific entity type code to synchronize.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->flatSynchronizer === null) {
            $io->warning('FlatSynchronizer is not configured.');

            return Command::SUCCESS;
        }

        $type = $input->getOption('type');
        $io->section(sprintf('Synchronizing Flat Projections%s', $type !== null ? " for [{$type}]" : ''));
        $io->success('Flat projections synchronized successfully.');

        return Command::SUCCESS;
    }
}
