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

namespace WireUpDev\Peav\Flat;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WireUpDev\Peav\Model\EntityTypeDefinition;

/**
 * Coordinates schema synchronization across configured flat storage strategies for entity types.
 */
class FlatSynchronizer
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly FlatStorageRegistry $flatStorageRegistry,
        private readonly FlatConfig $flatConfig = new FlatConfig(),
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Synchronizes all active flat storage strategies for the given entity type.
     */
    public function syncEntityType(EntityTypeDefinition $entityType): void
    {
        $strategies = $this->flatStorageRegistry->getStrategiesFor($entityType->getCode());

        foreach ($strategies as $strategy) {
            $this->logger->info(sprintf(
                'Synchronizing flat storage strategy "%s" for entity type "%s".',
                $strategy->getStrategyName(),
                $entityType->getCode(),
            ));
            $strategy->syncSchema($entityType);
        }
    }

    /**
     * Drops flat storage projections across all active strategies for the given entity type.
     */
    public function dropEntityType(EntityTypeDefinition $entityType): void
    {
        $strategies = $this->flatStorageRegistry->getStrategiesFor($entityType->getCode());

        foreach ($strategies as $strategy) {
            $this->logger->info(sprintf(
                'Dropping flat storage strategy "%s" for entity type "%s".',
                $strategy->getStrategyName(),
                $entityType->getCode(),
            ));
            $strategy->dropSchema($entityType);
        }
    }

    /**
     * Checks if all active flat storage strategies for the given entity type are synchronized.
     */
    public function isSynchronized(EntityTypeDefinition $entityType): bool
    {
        $strategies = $this->flatStorageRegistry->getStrategiesFor($entityType->getCode());

        if (empty($strategies)) {
            return true;
        }

        foreach ($strategies as $strategy) {
            if (!$strategy->isSynchronized($entityType)) {
                return false;
            }
        }

        return true;
    }

    public function getRegistry(): FlatStorageRegistry
    {
        return $this->flatStorageRegistry;
    }

    public function getConfig(): FlatConfig
    {
        return $this->flatConfig;
    }
}
