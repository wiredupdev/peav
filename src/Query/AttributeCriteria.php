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
 * Represents a query filtering condition on an attribute or standard entity property.
 */
final readonly class AttributeCriteria
{
    /**
     * @param string $attributeCode Attribute code or entity column name (id, created_at, updated_at)
     * @param string $operator Comparison operator (=, !=, <>, >, >=, <, <=, LIKE, NOT LIKE, IN, NOT IN, BETWEEN, IS NULL, IS NOT NULL)
     * @param mixed $value Comparison value, list of values (for IN/NOT IN/BETWEEN), or null (for IS NULL/IS NOT NULL)
     * @param string $conjunction Boolean operator ('AND' or 'OR')
     * @param bool $isEntityProperty Whether this criteria targets native entity columns (id, created_at, updated_at)
     */
    public function __construct(
        private string $attributeCode,
        private string $operator,
        private mixed $value = null,
        private string $conjunction = 'AND',
        private bool $isEntityProperty = false,
    ) {
    }

    public function getAttributeCode(): string
    {
        return $this->attributeCode;
    }

    public function getOperator(): string
    {
        return strtoupper(trim($this->operator));
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getConjunction(): string
    {
        return strtoupper(trim($this->conjunction));
    }

    public function isEntityProperty(): bool
    {
        return $this->isEntityProperty;
    }

    /**
     * Creates an equality criteria.
     */
    public static function eq(string $attributeCode, mixed $value, string $conjunction = 'AND'): self
    {
        return new self($attributeCode, '=', $value, $conjunction);
    }

    /**
     * Creates an IN criteria.
     *
     * @param list<mixed> $values
     */
    public static function in(string $attributeCode, array $values, string $conjunction = 'AND'): self
    {
        return new self($attributeCode, 'IN', $values, $conjunction);
    }

    /**
     * Creates a NOT IN criteria.
     *
     * @param list<mixed> $values
     */
    public static function notIn(string $attributeCode, array $values, string $conjunction = 'AND'): self
    {
        return new self($attributeCode, 'NOT IN', $values, $conjunction);
    }

    /**
     * Creates a BETWEEN criteria.
     */
    public static function between(string $attributeCode, mixed $from, mixed $to, string $conjunction = 'AND'): self
    {
        return new self($attributeCode, 'BETWEEN', [$from, $to], $conjunction);
    }

    /**
     * Creates an IS NULL criteria.
     */
    public static function isNull(string $attributeCode, string $conjunction = 'AND'): self
    {
        return new self($attributeCode, 'IS NULL', null, $conjunction);
    }

    /**
     * Creates an IS NOT NULL criteria.
     */
    public static function isNotNull(string $attributeCode, string $conjunction = 'AND'): self
    {
        return new self($attributeCode, 'IS NOT NULL', null, $conjunction);
    }

    /**
     * Creates a LIKE criteria.
     */
    public static function like(string $attributeCode, string $pattern, string $conjunction = 'AND'): self
    {
        return new self($attributeCode, 'LIKE', $pattern, $conjunction);
    }

    /**
     * Creates an entity primary key criteria.
     */
    public static function forEntityProperty(string $property, string $operator, mixed $value, string $conjunction = 'AND'): self
    {
        return new self($property, $operator, $value, $conjunction, true);
    }
}
