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

namespace WireUpDev\Peav\Query;

/**
 * Generates unique and deterministic SQL table aliases for dynamic attribute joins.
 */
class JoinAliasGenerator
{
    /**
     * @var array<string, string>
     */
    private array $attributeAliases = [];

    /**
     * @var array<string, string>
     */
    private array $valueAliases = [];

    private int $counter = 0;

    /**
     * Returns a deterministic alias for the attributes table join for the given attribute code.
     */
    public function getAttributeAlias(string $attributeCode): string
    {
        if (!isset($this->attributeAliases[$attributeCode])) {
            $sanitized = (string) preg_replace('/[^a-zA-Z0-9_]/', '_', $attributeCode);
            $this->attributeAliases[$attributeCode] = sprintf('a_%s_%d', $sanitized, ++$this->counter);
        }

        return $this->attributeAliases[$attributeCode];
    }

    /**
     * Returns a deterministic alias for the typed value table join for the given attribute code.
     */
    public function getValueAlias(string $attributeCode): string
    {
        if (!isset($this->valueAliases[$attributeCode])) {
            $sanitized = (string) preg_replace('/[^a-zA-Z0-9_]/', '_', $attributeCode);
            $this->valueAliases[$attributeCode] = sprintf('v_%s_%d', $sanitized, ++$this->counter);
        }

        return $this->valueAliases[$attributeCode];
    }

    /**
     * Resets internal alias mapping and counter.
     */
    public function reset(): void
    {
        $this->attributeAliases = [];
        $this->valueAliases = [];
        $this->counter = 0;
    }
}
