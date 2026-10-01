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

namespace WireUpDev\Peav\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use WireUpDev\Peav\Exception\AttributeInUseException;
use WireUpDev\Peav\Exception\AttributeNotFoundException;
use WireUpDev\Peav\Exception\EntityTypeNotFoundException;
use WireUpDev\Peav\Exception\FlatTableSyncException;
use WireUpDev\Peav\Exception\InvalidAttributeValueException;
use WireUpDev\Peav\Exception\PeavExceptionInterface;
use WireUpDev\Peav\Exception\SchemaSyncException;
use WireUpDev\Peav\Exception\UnsupportedTypeException;

final class ExceptionTest extends TestCase
{
    public function testExceptionsImplementPeavExceptionInterface(): void
    {
        $exceptions = [
            new AttributeInUseException('Attribute in use'),
            new AttributeNotFoundException('Attribute not found'),
            new EntityTypeNotFoundException('Entity type not found'),
            new FlatTableSyncException('Flat table sync error'),
            new InvalidAttributeValueException('Invalid attribute value'),
            new SchemaSyncException('Schema sync error'),
            new UnsupportedTypeException('Unsupported type'),
        ];

        foreach ($exceptions as $exception) {
            self::assertInstanceOf(PeavExceptionInterface::class, $exception);
            self::assertInstanceOf(\Throwable::class, $exception);
        }
    }

    public function testExceptionMessages(): void
    {
        $e = new AttributeInUseException('Attribute "sku" is in use by 5 entities.');
        self::assertSame('Attribute "sku" is in use by 5 entities.', $e->getMessage());
    }
}
