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

namespace WireUpDev\Peav\Tests\Unit\Query;

use PHPUnit\Framework\TestCase;
use WireUpDev\Peav\Query\AttributeCriteria;

class AttributeCriteriaTest extends TestCase
{
    public function testCreatesCriteriaWithDefaults(): void
    {
        $criteria = new AttributeCriteria('price', '>', 50.0);

        $this->assertSame('price', $criteria->getAttributeCode());
        $this->assertSame('>', $criteria->getOperator());
        $this->assertSame(50.0, $criteria->getValue());
        $this->assertSame('AND', $criteria->getConjunction());
        $this->assertFalse($criteria->isEntityProperty());
    }

    public function testCreatesOrCriteria(): void
    {
        $criteria = new AttributeCriteria('status', '=', 'active', 'OR');

        $this->assertSame('status', $criteria->getAttributeCode());
        $this->assertSame('=', $criteria->getOperator());
        $this->assertSame('active', $criteria->getValue());
        $this->assertSame('OR', $criteria->getConjunction());
    }

    public function testFactoryMethods(): void
    {
        $in = AttributeCriteria::in('sku', ['A', 'B']);
        $this->assertSame('IN', $in->getOperator());
        $this->assertSame(['A', 'B'], $in->getValue());

        $notIn = AttributeCriteria::notIn('sku', ['C']);
        $this->assertSame('NOT IN', $notIn->getOperator());

        $between = AttributeCriteria::between('price', 10, 20);
        $this->assertSame('BETWEEN', $between->getOperator());
        $this->assertSame([10, 20], $between->getValue());

        $isNull = AttributeCriteria::isNull('description');
        $this->assertSame('IS NULL', $isNull->getOperator());
        $this->assertNull($isNull->getValue());

        $isNotNull = AttributeCriteria::isNotNull('description');
        $this->assertSame('IS NOT NULL', $isNotNull->getOperator());
        $this->assertNull($isNotNull->getValue());
    }
}
