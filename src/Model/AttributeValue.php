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

use WireUpDev\Peav\Type\StorageBucket;
use WireUpDev\Peav\Type\TypeInterface;

/**
 * Immutable container for an attribute value associated with its type and storage bucket.
 */
final readonly class AttributeValue
{
    public function __construct(
        private string $attributeCode,
        private mixed $value,
        private TypeInterface $type,
    ) {
    }

    public function getAttributeCode(): string
    {
        return $this->attributeCode;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getType(): TypeInterface
    {
        return $this->type;
    }

    public function getStorageBucket(): StorageBucket
    {
        return $this->type->getStorageBucket();
    }
}
