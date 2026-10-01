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
 * Immutable definition of an entity type and its attached preset attributes.
 */
final readonly class EntityTypeDefinition
{
    /**
     * @var array<string, PresetAttribute>
     */
    private array $presets;

    /**
     * @param string $code Unique code for entity type (e.g. 'product', 'customer')
     * @param string $name Human-readable entity type name
     * @param string|null $description Optional description
     * @param class-string<EavEntity>|null $customClass Optional custom PHP entity class extending EavEntity
     * @param array<int|string, PresetAttribute> $presets Attached preset attributes
     */
    public function __construct(
        private string $code,
        private string $name,
        private ?string $description = null,
        private ?string $customClass = null,
        array $presets = [],
    ) {
        $presetMap = [];
        foreach ($presets as $preset) {
            $presetMap[$preset->getAttributeCode()] = $preset;
        }
        $this->presets = $presetMap;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return class-string<EavEntity>|null
     */
    public function getCustomClass(): ?string
    {
        return $this->customClass;
    }

    /**
     * @return array<string, PresetAttribute>
     */
    public function getPresets(): array
    {
        return $this->presets;
    }

    public function hasPreset(string $attributeCode): bool
    {
        return isset($this->presets[$attributeCode]);
    }

    public function getPreset(string $attributeCode): ?PresetAttribute
    {
        return $this->presets[$attributeCode] ?? null;
    }

    /**
     * Returns a new instance with the added or replaced preset attribute.
     */
    public function withPreset(PresetAttribute $preset): self
    {
        $presets = $this->presets;
        $presets[$preset->getAttributeCode()] = $preset;

        return new self(
            $this->code,
            $this->name,
            $this->description,
            $this->customClass,
            array_values($presets),
        );
    }

    /**
     * Returns a new instance without the specified preset attribute.
     */
    public function withoutPreset(string $attributeCode): self
    {
        $presets = $this->presets;
        unset($presets[$attributeCode]);

        return new self(
            $this->code,
            $this->name,
            $this->description,
            $this->customClass,
            array_values($presets),
        );
    }
}
