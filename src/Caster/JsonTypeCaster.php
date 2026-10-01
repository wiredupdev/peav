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

final readonly class JsonTypeCaster implements TypeCasterInterface
{
    public function convertToDatabaseValue(mixed $value, TypeInterface $type, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            // Validate if it is valid json
            try {
                json_decode($value, true, 512, JSON_THROW_ON_ERROR);

                return $value;
            } catch (\JsonException) {
                return json_encode($value, JSON_THROW_ON_ERROR);
            }
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidAttributeValueException(sprintf('Failed to JSON encode value for attribute "%s": %s', $type->getName(), $e->getMessage()), 0, $e);
        }
    }

    public function convertToPHPValue(mixed $value, TypeInterface $type, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            return $value;
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $value;
        }
    }
}
