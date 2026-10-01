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

use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\TestCase;
use WireUpDev\Peav\Caster\BlobTypeCaster;
use WireUpDev\Peav\Caster\BooleanTypeCaster;
use WireUpDev\Peav\Caster\DateTimeTypeCaster;
use WireUpDev\Peav\Caster\DecimalTypeCaster;
use WireUpDev\Peav\Caster\IntegerTypeCaster;
use WireUpDev\Peav\Caster\JsonTypeCaster;
use WireUpDev\Peav\Caster\StringTypeCaster;
use WireUpDev\Peav\Caster\TextTypeCaster;
use WireUpDev\Peav\Exception\InvalidAttributeValueException;
use WireUpDev\Peav\Type\AttributeType;

final class TypeCasterTest extends TestCase
{
    private SQLitePlatform $platform;

    protected function setUp(): void
    {
        $this->platform = new SQLitePlatform();
    }

    public function testStringTypeCaster(): void
    {
        $caster = new StringTypeCaster();

        self::assertSame('hello', $caster->convertToDatabaseValue('hello', AttributeType::String, $this->platform));
        self::assertSame('123', $caster->convertToDatabaseValue(123, AttributeType::String, $this->platform));
        self::assertNull($caster->convertToDatabaseValue(null, AttributeType::String, $this->platform));

        self::assertSame('world', $caster->convertToPHPValue('world', AttributeType::String, $this->platform));
        self::assertNull($caster->convertToPHPValue(null, AttributeType::String, $this->platform));
    }

    public function testIntegerTypeCaster(): void
    {
        $caster = new IntegerTypeCaster();

        self::assertSame(42, $caster->convertToDatabaseValue(42, AttributeType::Integer, $this->platform));
        self::assertSame(100, $caster->convertToDatabaseValue('100', AttributeType::Integer, $this->platform));
        self::assertNull($caster->convertToDatabaseValue(null, AttributeType::Integer, $this->platform));

        self::assertSame(42, $caster->convertToPHPValue('42', AttributeType::Integer, $this->platform));
        self::assertNull($caster->convertToPHPValue(null, AttributeType::Integer, $this->platform));

        $this->expectException(InvalidAttributeValueException::class);
        $caster->convertToDatabaseValue('not-an-integer', AttributeType::Integer, $this->platform);
    }

    public function testDecimalTypeCaster(): void
    {
        $caster = new DecimalTypeCaster();

        self::assertSame('99.99', $caster->convertToDatabaseValue('99.99', AttributeType::Decimal, $this->platform));
        self::assertSame('100.5', $caster->convertToDatabaseValue(100.5, AttributeType::Decimal, $this->platform));
        self::assertNull($caster->convertToDatabaseValue(null, AttributeType::Decimal, $this->platform));

        self::assertSame('99.99', $caster->convertToPHPValue('99.99', AttributeType::Decimal, $this->platform));
        self::assertNull($caster->convertToPHPValue(null, AttributeType::Decimal, $this->platform));

        $this->expectException(InvalidAttributeValueException::class);
        $caster->convertToDatabaseValue('invalid-number', AttributeType::Decimal, $this->platform);
    }

    public function testDateTimeTypeCaster(): void
    {
        $caster = new DateTimeTypeCaster();
        $dt = new \DateTimeImmutable('2026-09-30 15:30:00');

        $dbVal = $caster->convertToDatabaseValue($dt, AttributeType::DateTimeImmutable, $this->platform);
        self::assertNotNull($dbVal);

        $phpVal = $caster->convertToPHPValue('2026-09-30 15:30:00', AttributeType::DateTimeImmutable, $this->platform);
        self::assertInstanceOf(\DateTimeImmutable::class, $phpVal);
        self::assertSame('2026-09-30 15:30:00', $phpVal->format('Y-m-d H:i:s'));
        self::assertNull($caster->convertToPHPValue(null, AttributeType::DateTimeImmutable, $this->platform));
    }

    public function testBooleanTypeCaster(): void
    {
        $caster = new BooleanTypeCaster();

        self::assertTrue($caster->convertToDatabaseValue(true, AttributeType::Boolean, $this->platform));
        self::assertFalse($caster->convertToDatabaseValue(false, AttributeType::Boolean, $this->platform));
        self::assertNull($caster->convertToDatabaseValue(null, AttributeType::Boolean, $this->platform));

        self::assertTrue($caster->convertToPHPValue(1, AttributeType::Boolean, $this->platform));
        self::assertFalse($caster->convertToPHPValue(0, AttributeType::Boolean, $this->platform));
        self::assertNull($caster->convertToPHPValue(null, AttributeType::Boolean, $this->platform));
    }

    public function testJsonTypeCaster(): void
    {
        $caster = new JsonTypeCaster();
        $payload = ['tags' => ['php', 'eav'], 'active' => true];

        $dbVal = $caster->convertToDatabaseValue($payload, AttributeType::Json, $this->platform);
        self::assertIsString($dbVal);
        self::assertJson($dbVal);

        $phpVal = $caster->convertToPHPValue($dbVal, AttributeType::Json, $this->platform);
        self::assertSame($payload, $phpVal);
        self::assertNull($caster->convertToPHPValue(null, AttributeType::Json, $this->platform));
    }

    public function testTextTypeCaster(): void
    {
        $caster = new TextTypeCaster();

        self::assertSame('Long text content', $caster->convertToDatabaseValue('Long text content', AttributeType::Text, $this->platform));
        self::assertSame('Long text content', $caster->convertToPHPValue('Long text content', AttributeType::Text, $this->platform));
    }

    public function testBlobTypeCaster(): void
    {
        $caster = new BlobTypeCaster();
        $binary = "\x00\x01\x02\xFF";

        $dbVal = $caster->convertToDatabaseValue($binary, AttributeType::Blob, $this->platform);
        self::assertSame($binary, $dbVal);

        $phpVal = $caster->convertToPHPValue($binary, AttributeType::Blob, $this->platform);
        self::assertSame($binary, $phpVal);
    }
}
