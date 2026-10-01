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

final readonly class DateTimeTypeCaster implements TypeCasterInterface
{
    public function convertToDatabaseValue(mixed $value, TypeInterface $type, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            try {
                $dt = new \DateTimeImmutable($value);

                return $dt->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                throw new InvalidAttributeValueException(sprintf('Invalid datetime format "%s" for attribute "%s".', $value, $type->getName()), 0, $e);
            }
        }

        throw new InvalidAttributeValueException(sprintf('Value for attribute type "%s" must be a DateTimeInterface or date string, %s given.', $type->getName(), get_debug_type($value)));
    }

    public function convertToPHPValue(mixed $value, TypeInterface $type, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($value);
        }

        if (is_string($value)) {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
