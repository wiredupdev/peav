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

/**
 * CLI command to batch re-index normalized EAV entities into flat tables and search indexes.
 */
#[AsCommand(name: 'peav:flat:reindex', description: 'Re-indexes entities into physical flat tables and non-SQL projections.')]
class FlatTableReindexCommand extends Command
{
    public function __construct(
        private readonly ?object $flatIndexer = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Specific entity type code to re-index.')
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Batch chunk size for processing entities.', '500');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $type = $input->getOption('type');
        $batchSize = (int) $input->getOption('batch-size');

        $io->section(sprintf('Re-indexing Flat Projections (Batch Size: %d)%s', $batchSize, $type !== null ? " for [{$type}]" : ''));

        if ($this->flatIndexer !== null && method_exists($this->flatIndexer, 'reindexAll')) {
            $this->flatIndexer->reindexAll($type, $batchSize);
        }

        $io->success('Flat projection re-indexing completed successfully.');

        return Command::SUCCESS;
    }
}
