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
use WireUpDev\Peav\Query\JoinAliasGenerator;

class JoinAliasGeneratorTest extends TestCase
{
    private JoinAliasGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new JoinAliasGenerator();
    }

    public function testGeneratesDeterministicAliasesForAttribute(): void
    {
        $attrAlias1 = $this->generator->getAttributeAlias('sku');
        $valAlias1 = $this->generator->getValueAlias('sku');

        $this->assertNotEmpty($attrAlias1);
        $this->assertNotEmpty($valAlias1);
        $this->assertNotSame($attrAlias1, $valAlias1);

        // Same attribute gets same alias
        $this->assertSame($attrAlias1, $this->generator->getAttributeAlias('sku'));
        $this->assertSame($valAlias1, $this->generator->getValueAlias('sku'));
    }

    public function testGeneratesDifferentAliasesForDifferentAttributes(): void
    {
        $skuAttr = $this->generator->getAttributeAlias('sku');
        $priceAttr = $this->generator->getAttributeAlias('price');

        $this->assertNotSame($skuAttr, $priceAttr);
    }

    public function testSanitizesSpecialCharacters(): void
    {
        $alias = $this->generator->getAttributeAlias('custom.attr-name');
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_]+$/', $alias);
    }

    public function testResetClearsAliases(): void
    {
        $this->generator->getAttributeAlias('sku'); // counter = 1
        $alias1 = $this->generator->getAttributeAlias('price'); // counter = 2 => a_price_2
        $this->generator->reset();
        $alias2 = $this->generator->getAttributeAlias('price'); // counter = 1 => a_price_1

        $this->assertNotSame($alias1, $alias2);
    }
}
