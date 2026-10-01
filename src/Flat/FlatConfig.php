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

/**
 * Strategy mode for flat read projections.
 */
enum FlatStrategyMode: string
{
    case Physical = 'physical';
    case View = 'view';
    case Custom = 'custom';
    case None = 'none';
}

/**
 * Configuration options for the flat table and view projection subsystem.
 */
final readonly class FlatConfig
{
    /**
     * @param FlatStrategyMode $defaultMode Default strategy mode for entity types
     * @param array<string, FlatStrategyMode> $entityTypeModes Per-entity-type strategy mode overrides
     * @param bool $autoSyncSchema Whether to auto-sync flat table/view schemas on entity type preset updates
     * @param bool $realtimeIndexing Whether to update flat projections synchronously on entity save/delete
     */
    public function __construct(
        private FlatStrategyMode $defaultMode = FlatStrategyMode::Physical,
        private array $entityTypeModes = [],
        private bool $autoSyncSchema = false,
        private bool $realtimeIndexing = true,
    ) {
    }

    public function getDefaultMode(): FlatStrategyMode
    {
        return $this->defaultMode;
    }

    public function getModeFor(string $entityTypeCode): FlatStrategyMode
    {
        return $this->entityTypeModes[$entityTypeCode] ?? $this->defaultMode;
    }

    public function isAutoSyncSchema(): bool
    {
        return $this->autoSyncSchema;
    }

    public function isRealtimeIndexing(): bool
    {
        return $this->realtimeIndexing;
    }
}
