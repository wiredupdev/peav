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

use WireUpDev\Peav\Exception\FlatTableSyncException;

/**
 * Registry managing active flat storage strategies (physical SQL, dynamic SQL views, non-SQL stores).
 */
class FlatStorageRegistry
{
    /**
     * @var array<string, FlatStorageStrategyInterface>
     */
    private array $strategies = [];

    /**
     * @var array<string, list<string>> Map of entity type code to list of active strategy names
     */
    private array $entityTypeMappings = [];

    public function __construct(
        private readonly FlatConfig $flatConfig = new FlatConfig(),
    ) {
    }

    /**
     * Registers a flat storage strategy.
     */
    public function registerStrategy(FlatStorageStrategyInterface $strategy): self
    {
        $this->strategies[$strategy->getStrategyName()] = $strategy;

        return $this;
    }

    /**
     * Maps an entity type to one or more strategy names.
     *
     * @param list<string>|string $strategyNames
     */
    public function mapEntityType(string $entityTypeCode, array|string $strategyNames): self
    {
        $this->entityTypeMappings[$entityTypeCode] = (array) $strategyNames;

        return $this;
    }

    public function hasStrategy(string $strategyName): bool
    {
        return isset($this->strategies[$strategyName]);
    }

    public function getStrategy(string $strategyName): FlatStorageStrategyInterface
    {
        if (!isset($this->strategies[$strategyName])) {
            throw new FlatTableSyncException(sprintf('Flat storage strategy "%s" is not registered.', $strategyName));
        }

        return $this->strategies[$strategyName];
    }

    /**
     * Returns all active strategies configured for an entity type.
     *
     * @return list<FlatStorageStrategyInterface>
     */
    public function getStrategiesFor(string $entityTypeCode): array
    {
        $mode = $this->flatConfig->getModeFor($entityTypeCode);

        if ($mode === FlatStrategyMode::None) {
            return [];
        }

        if (isset($this->entityTypeMappings[$entityTypeCode])) {
            $strategies = [];
            foreach ($this->entityTypeMappings[$entityTypeCode] as $name) {
                if ($this->hasStrategy($name)) {
                    $strategies[] = $this->getStrategy($name);
                }
            }

            return $strategies;
        }

        $defaultStrategyName = $mode->value;
        if ($this->hasStrategy($defaultStrategyName)) {
            return [$this->getStrategy($defaultStrategyName)];
        }

        return [];
    }

    /**
     * @return array<string, FlatStorageStrategyInterface>
     */
    public function all(): array
    {
        return $this->strategies;
    }
}
