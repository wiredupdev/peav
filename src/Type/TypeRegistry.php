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

namespace WireUpDev\Peav\Type;

use WireUpDev\Peav\Exception\UnsupportedTypeException;

/**
 * Registry managing built-in and user-defined EAV attribute types.
 */
class TypeRegistry
{
    /**
     * @var array<string, TypeInterface>
     */
    private array $types = [];

    public function __construct()
    {
        $this->registerBuiltinTypes();
    }

    /**
     * Registers a new or custom attribute type.
     */
    public function register(TypeInterface $type): self
    {
        $this->types[$type->getName()] = $type;

        return $this;
    }

    /**
     * Checks if a type is registered by name.
     */
    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }

    /**
     * Retrieves a registered type by name or throws UnsupportedTypeException.
     *
     * @throws UnsupportedTypeException
     */
    public function get(string $name): TypeInterface
    {
        if (!isset($this->types[$name])) {
            throw new UnsupportedTypeException(sprintf('Attribute type "%s" is not registered in TypeRegistry.', $name));
        }

        return $this->types[$name];
    }

    /**
     * Resolves an AttributeType enum, TypeInterface instance, or type name string to TypeInterface.
     */
    public function resolve(TypeInterface|string $type): TypeInterface
    {
        if ($type instanceof TypeInterface) {
            return $type;
        }

        return $this->get($type);
    }

    /**
     * Returns all registered types.
     *
     * @return array<string, TypeInterface>
     */
    public function all(): array
    {
        return $this->types;
    }

    private function registerBuiltinTypes(): void
    {
        foreach (AttributeType::cases() as $case) {
            $this->register($case);
        }
    }
}
