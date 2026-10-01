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
use WireUpDev\Peav\Type\TypeInterface;

final readonly class BooleanTypeCaster implements TypeCasterInterface
{
    public function convertToDatabaseValue(mixed $value, TypeInterface $type, AbstractPlatform $platform): ?bool
    {
        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }

    public function convertToPHPValue(mixed $value, TypeInterface $type, AbstractPlatform $platform): ?bool
    {
        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }
}
