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

use Doctrine\DBAL\Types\Types;

/**
 * Built-in attribute types supported natively by Peav.
 */
enum AttributeType: string implements TypeInterface
{
    case String = 'string';
    case Guid = 'guid';
    case Integer = 'integer';
    case BigInt = 'bigint';
    case Decimal = 'decimal';
    case Float = 'float';
    case DateTimeImmutable = 'datetime_immutable';
    case DateImmutable = 'date_immutable';
    case Boolean = 'boolean';
    case Text = 'text';
    case Json = 'json';
    case Blob = 'blob';

    public function getName(): string
    {
        return $this->value;
    }

    public function getStorageBucket(): StorageBucket
    {
        return match ($this) {
            self::String, self::Guid => StorageBucket::String,
            self::Integer, self::BigInt => StorageBucket::Integer,
            self::Decimal, self::Float => StorageBucket::Decimal,
            self::DateTimeImmutable, self::DateImmutable => StorageBucket::DateTime,
            self::Boolean => StorageBucket::Boolean,
            self::Text => StorageBucket::Text,
            self::Json => StorageBucket::Json,
            self::Blob => StorageBucket::Blob,
        };
    }

    public function getDbalTypeName(): string
    {
        return match ($this) {
            self::String => Types::STRING,
            self::Guid => Types::GUID,
            self::Integer => Types::INTEGER,
            self::BigInt => Types::BIGINT,
            self::Decimal => Types::DECIMAL,
            self::Float => Types::FLOAT,
            self::DateTimeImmutable => Types::DATETIME_IMMUTABLE,
            self::DateImmutable => Types::DATE_IMMUTABLE,
            self::Boolean => Types::BOOLEAN,
            self::Text => Types::TEXT,
            self::Json => Types::JSON,
            self::Blob => Types::BLOB,
        };
    }
}
