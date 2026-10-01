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

namespace WireUpDev\Peav\Model;

/**
 * Base domain model for an EAV entity instance.
 */
class EavEntity
{
    /**
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    protected ?\DateTimeInterface $createdAt = null;

    protected ?\DateTimeInterface $updatedAt = null;

    /**
     * @param string $entityType Entity type code (e.g. 'product')
     * @param int|string|null $id Entity primary identifier
     * @param array<string, mixed> $attributes Initial dynamic attribute key-value map
     */
    public function __construct(
        protected string $entityType,
        protected int|string|null $id = null,
        array $attributes = [],
    ) {
        $this->attributes = $attributes;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getId(): int|string|null
    {
        return $this->id;
    }

    public function setId(int|string|null $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Retrieves an attribute value by code with optional fallback.
     */
    public function get(string $attribute, mixed $default = null): mixed
    {
        return  $this->attributes[$attribute] ?? $default;
    }

    /**
     * Sets an attribute value by code.
     */
    public function set(string $attribute, mixed $value): static
    {
        $this->attributes[$attribute] = $value;

        return $this;
    }

    /**
     * Checks if an attribute exists on the entity.
     */
    public function has(string $attribute): bool
    {
        return array_key_exists($attribute, $this->attributes);
    }

    /**
     * Removes an attribute from the entity.
     */
    public function remove(string $attribute): static
    {
        unset($this->attributes[$attribute]);

        return $this;
    }

    /**
     * Returns all dynamic attributes as an associative array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * Replaces or merges all attributes.
     *
     * @param array<string, mixed> $attributes
     */
    public function replaceAttributes(array $attributes): static
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
