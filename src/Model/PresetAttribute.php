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
 * Immutable value object associating an attribute with an entity type preset configuration.
 */
final readonly class PresetAttribute
{
    /**
     * @param AttributeDefinition $attribute The global attribute definition
     * @param bool $isRequired Whether the attribute is required for this entity type
     * @param mixed $defaultValue Default value when none is provided
     * @param int $position Visual or processing ordering index
     * @param array<string, mixed> $customMetadata Additional configuration or rules
     */
    public function __construct(
        private AttributeDefinition $attribute,
        private bool $isRequired = false,
        private mixed $defaultValue = null,
        private int $position = 0,
        private array $customMetadata = [],
    ) {
    }

    public function getAttribute(): AttributeDefinition
    {
        return $this->attribute;
    }

    public function getAttributeCode(): string
    {
        return $this->attribute->getCode();
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomMetadata(): array
    {
        return $this->customMetadata;
    }
}
