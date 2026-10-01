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

use WireUpDev\Peav\Type\TypeInterface;

/**
 * Immutable definition of a global reusable EAV attribute.
 */
final readonly class AttributeDefinition
{
    /**
     * @param string $code Unique code for the attribute (e.g. 'sku', 'price')
     * @param TypeInterface $type The attribute type definition
     * @param string $name Human-readable attribute name
     * @param string|null $description Optional description
     * @param array<string, mixed> $validationRules Optional validation constraints/rules
     */
    public function __construct(
        private string $code,
        private TypeInterface $type,
        private string $name,
        private ?string $description = null,
        private array $validationRules = [],
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getType(): TypeInterface
    {
        return $this->type;
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
     * @return array<string, mixed>
     */
    public function getValidationRules(): array
    {
        return $this->validationRules;
    }
}
