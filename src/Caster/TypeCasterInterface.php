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

/**
 * Interface contract for converting values between PHP domain objects and database representations.
 */
interface TypeCasterInterface
{
    /**
     * Converts a PHP value to its database-compatible representation.
     */
    public function convertToDatabaseValue(mixed $value, TypeInterface $type, AbstractPlatform $platform): mixed;

    /**
     * Converts a database value to its PHP domain representation.
     */
    public function convertToPHPValue(mixed $value, TypeInterface $type, AbstractPlatform $platform): mixed;
}
