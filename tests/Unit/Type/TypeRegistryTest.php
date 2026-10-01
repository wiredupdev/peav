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

namespace WireUpDev\Peav\Tests\Unit\Type;

use PHPUnit\Framework\TestCase;
use WireUpDev\Peav\Exception\UnsupportedTypeException;
use WireUpDev\Peav\Type\AttributeType;
use WireUpDev\Peav\Type\StorageBucket;
use WireUpDev\Peav\Type\TypeInterface;
use WireUpDev\Peav\Type\TypeRegistry;

final class TypeRegistryTest extends TestCase
{
    public function testBuiltinTypesArePreRegistered(): void
    {
        $registry = new TypeRegistry();

        $builtinNames = [
            'string', 'guid', 'integer', 'bigint', 'decimal', 'float',
            'datetime_immutable', 'date_immutable', 'boolean', 'text', 'json', 'blob',
        ];

        foreach ($builtinNames as $name) {
            self::assertTrue($registry->has($name), "Expected type {$name} to be registered");
            $type = $registry->get($name);
            self::assertSame($name, $type->getName());
            self::assertInstanceOf(StorageBucket::class, $type->getStorageBucket());
        }
    }

    public function testAttributeTypeEnumMapping(): void
    {
        self::assertSame(StorageBucket::String, AttributeType::String->getStorageBucket());
        self::assertSame(StorageBucket::String, AttributeType::Guid->getStorageBucket());
        self::assertSame(StorageBucket::Integer, AttributeType::Integer->getStorageBucket());
        self::assertSame(StorageBucket::Integer, AttributeType::BigInt->getStorageBucket());
        self::assertSame(StorageBucket::Decimal, AttributeType::Decimal->getStorageBucket());
        self::assertSame(StorageBucket::Decimal, AttributeType::Float->getStorageBucket());
        self::assertSame(StorageBucket::DateTime, AttributeType::DateTimeImmutable->getStorageBucket());
        self::assertSame(StorageBucket::DateTime, AttributeType::DateImmutable->getStorageBucket());
        self::assertSame(StorageBucket::Boolean, AttributeType::Boolean->getStorageBucket());
        self::assertSame(StorageBucket::Text, AttributeType::Text->getStorageBucket());
        self::assertSame(StorageBucket::Json, AttributeType::Json->getStorageBucket());
        self::assertSame(StorageBucket::Blob, AttributeType::Blob->getStorageBucket());
    }

    public function testRegisterCustomType(): void
    {
        $registry = new TypeRegistry();

        $customType = new class implements TypeInterface {
            public function getName(): string
            {
                return 'money';
            }

            public function getStorageBucket(): StorageBucket
            {
                return StorageBucket::Decimal;
            }

            public function getDbalTypeName(): string
            {
                return 'decimal';
            }
        };

        $registry->register($customType);
        self::assertTrue($registry->has('money'));
        self::assertSame($customType, $registry->get('money'));
    }

    public function testGetUnregisteredTypeThrowsException(): void
    {
        $registry = new TypeRegistry();
        $this->expectException(UnsupportedTypeException::class);
        $registry->get('non_existent_type');
    }
}
