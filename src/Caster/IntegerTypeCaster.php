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

namespace WireUpDev\Peav\Caster;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use WireUpDev\Peav\Exception\InvalidAttributeValueException;
use WireUpDev\Peav\Type\TypeInterface;

final readonly class IntegerTypeCaster implements TypeCasterInterface
{
    public function convertToDatabaseValue(mixed $value, TypeInterface $type, AbstractPlatform $platform): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new InvalidAttributeValueException(sprintf('Value for attribute type "%s" must be an integer, %s given.', $type->getName(), get_debug_type($value)));
    }

    public function convertToPHPValue(mixed $value, TypeInterface $type, AbstractPlatform $platform): ?int
    {
        if ($value === null) {
            return null;
        }

        return (int) $value;
    }
}
