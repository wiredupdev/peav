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

namespace WireUpDev\Peav\Tests\Unit\Caster;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;
use WireUpDev\Peav\Caster\BlobTypeCaster;
use WireUpDev\Peav\Caster\BooleanTypeCaster;
use WireUpDev\Peav\Caster\CasterRegistry;
use WireUpDev\Peav\Caster\DateTimeTypeCaster;
use WireUpDev\Peav\Caster\DecimalTypeCaster;
use WireUpDev\Peav\Caster\IntegerTypeCaster;
use WireUpDev\Peav\Caster\JsonTypeCaster;
use WireUpDev\Peav\Caster\StringTypeCaster;
use WireUpDev\Peav\Caster\TextTypeCaster;
use WireUpDev\Peav\Caster\TypeCasterInterface;
use WireUpDev\Peav\Exception\CasterNotFoundException;
use WireUpDev\Peav\Type\AttributeType;
use WireUpDev\Peav\Type\StorageBucket;
use WireUpDev\Peav\Type\TypeInterface;

final class CasterRegistryTest extends TestCase
{
    public function testBuiltinStorageBucketCastersAreRegistered(): void
    {
        $registry = new CasterRegistry();

        self::assertTrue($registry->has(StorageBucket::String));
        self::assertTrue($registry->has(StorageBucket::Integer));
        self::assertTrue($registry->has(StorageBucket::Decimal));
        self::assertTrue($registry->has(StorageBucket::DateTime));
        self::assertTrue($registry->has(StorageBucket::Boolean));
        self::assertTrue($registry->has(StorageBucket::Text));
        self::assertTrue($registry->has(StorageBucket::Json));
        self::assertTrue($registry->has(StorageBucket::Blob));

        self::assertInstanceOf(StringTypeCaster::class, $registry->get(StorageBucket::String));
        self::assertInstanceOf(IntegerTypeCaster::class, $registry->get(StorageBucket::Integer));
        self::assertInstanceOf(DecimalTypeCaster::class, $registry->get(StorageBucket::Decimal));
        self::assertInstanceOf(DateTimeTypeCaster::class, $registry->get(StorageBucket::DateTime));
        self::assertInstanceOf(BooleanTypeCaster::class, $registry->get(StorageBucket::Boolean));
        self::assertInstanceOf(TextTypeCaster::class, $registry->get(StorageBucket::Text));
        self::assertInstanceOf(JsonTypeCaster::class, $registry->get(StorageBucket::Json));
        self::assertInstanceOf(BlobTypeCaster::class, $registry->get(StorageBucket::Blob));

        self::assertCount(8, $registry->all());
    }

    public function testRegisterAndRetrieveCustomCasterForBucket(): void
    {
        $registry = new CasterRegistry();
        $customCaster = new class implements TypeCasterInterface {
            public function convertToDatabaseValue(mixed $value, TypeInterface $type, AbstractPlatform $platform): mixed
            {
                return $value;
            }

            public function convertToPHPValue(mixed $value, TypeInterface $type, AbstractPlatform $platform): mixed
            {
                return $value;
            }
        };

        $registry->register(StorageBucket::String, $customCaster);

        self::assertSame($customCaster, $registry->get(StorageBucket::String));
        self::assertSame($customCaster, $registry->getForBucket(StorageBucket::String));
    }

    public function testRegisterAndRetrieveCustomCasterForCustomTypeName(): void
    {
        $registry = new CasterRegistry();
        $moneyCaster = new class implements TypeCasterInterface {
            public function convertToDatabaseValue(mixed $value, TypeInterface $type, AbstractPlatform $platform): mixed
            {
                return $value !== null ? (string) $value : null;
            }

            public function convertToPHPValue(mixed $value, TypeInterface $type, AbstractPlatform $platform): mixed
            {
                return $value;
            }
        };

        $registry->register('money', $moneyCaster);

        self::assertTrue($registry->has('money'));
        self::assertSame($moneyCaster, $registry->get('money'));
    }

    public function testGetForTypeResolvesSpecificTypeBeforeBucketFallback(): void
    {
        $registry = new CasterRegistry();

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

        // Fallback to Decimal caster when not specifically registered
        self::assertInstanceOf(DecimalTypeCaster::class, $registry->getForType($customType));

        // Register custom caster specifically for 'money'
        $moneyCaster = new class implements TypeCasterInterface {
            public function convertToDatabaseValue(mixed $value, TypeInterface $type, AbstractPlatform $platform): mixed
            {
                return $value;
            }

            public function convertToPHPValue(mixed $value, TypeInterface $type, AbstractPlatform $platform): mixed
            {
                return $value;
            }
        };

        $registry->register('money', $moneyCaster);

        self::assertSame($moneyCaster, $registry->getForType($customType));
        // Builtin decimal remains intact
        self::assertInstanceOf(DecimalTypeCaster::class, $registry->getForBucket(StorageBucket::Decimal));
    }

    public function testGetForBuiltinAttributeType(): void
    {
        $registry = new CasterRegistry();

        self::assertInstanceOf(StringTypeCaster::class, $registry->getForType(AttributeType::String));
        self::assertInstanceOf(IntegerTypeCaster::class, $registry->getForType(AttributeType::Integer));
        self::assertInstanceOf(DecimalTypeCaster::class, $registry->getForType(AttributeType::Decimal));
        self::assertInstanceOf(DateTimeTypeCaster::class, $registry->getForType(AttributeType::DateTimeImmutable));
        self::assertInstanceOf(BooleanTypeCaster::class, $registry->getForType(AttributeType::Boolean));
        self::assertInstanceOf(TextTypeCaster::class, $registry->getForType(AttributeType::Text));
        self::assertInstanceOf(JsonTypeCaster::class, $registry->getForType(AttributeType::Json));
        self::assertInstanceOf(BlobTypeCaster::class, $registry->getForType(AttributeType::Blob));
    }

    public function testGetUnregisteredCasterThrowsException(): void
    {
        $registry = new CasterRegistry();

        $this->expectException(CasterNotFoundException::class);
        $this->expectExceptionMessage('Type caster for "non_existent" is not registered in CasterRegistry.');

        $registry->get('non_existent');
    }
}
