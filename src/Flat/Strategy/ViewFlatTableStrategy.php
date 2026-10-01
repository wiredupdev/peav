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

namespace WireUpDev\Peav\Flat\Strategy;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WireUpDev\Peav\Flat\FlatConfig;
use WireUpDev\Peav\Flat\FlatStorageStrategyInterface;
use WireUpDev\Peav\Flat\FlatViewBuilder;
use WireUpDev\Peav\Model\EavEntity;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Schema\TableConfig;

/**
 * Creates dynamic SQL views providing zero-storage, real-time flat reading capabilities.
 */
class ViewFlatTableStrategy implements FlatStorageStrategyInterface
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly Connection $connection,
        private readonly FlatViewBuilder $flatViewBuilder,
        private readonly TableConfig $tableConfig = new TableConfig(),
        private readonly FlatConfig $flatConfig = new FlatConfig(),
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function getStrategyName(): string
    {
        return 'view';
    }

    public function syncSchema(EntityTypeDefinition $entityType): void
    {
        $this->dropSchema($entityType);

        $sql = $this->flatViewBuilder->buildCreateViewSql($entityType);
        $this->logger->info(sprintf('Creating dynamic flat SQL view for entity "%s".', $entityType->getCode()));
        $this->connection->executeStatement($sql);
    }

    public function dropSchema(EntityTypeDefinition $entityType): void
    {
        $sql = $this->flatViewBuilder->buildDropViewSql($entityType);
        $this->logger->info(sprintf('Dropping dynamic flat SQL view for entity "%s".', $entityType->getCode()));
        $this->connection->executeStatement($sql);
    }

    public function isSynchronized(EntityTypeDefinition $entityType): bool
    {
        $viewName = $this->tableConfig->getFlatViewName($entityType->getCode());
        try {
            $this->connection->executeQuery(sprintf('SELECT 1 FROM %s LIMIT 1', $viewName));

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function indexEntity(EavEntity $entity, EntityTypeDefinition $entityType): void
    {
        // Views reflect underlying EAV data immediately; no indexing needed.
    }

    public function removeEntity(int|string $entityId, EntityTypeDefinition $entityType): void
    {
        // Views reflect underlying EAV data immediately; no indexing needed.
    }

    public function batchIndex(array $entities, EntityTypeDefinition $entityType): void
    {
        // Views reflect underlying EAV data immediately; no indexing needed.
    }

    public function getTableConfig(): TableConfig
    {
        return $this->tableConfig;
    }

    public function getFlatConfig(): FlatConfig
    {
        return $this->flatConfig;
    }
}
