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

namespace WireUpDev\Peav\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use WireUpDev\Peav\Model\AttributeDefinition;
use WireUpDev\Peav\Model\AttributeValue;
use WireUpDev\Peav\Model\EavEntity;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Model\PresetAttribute;
use WireUpDev\Peav\Type\AttributeType;
use WireUpDev\Peav\Type\StorageBucket;

final class ModelTest extends TestCase
{
    public function testAttributeDefinition(): void
    {
        $attr = new AttributeDefinition('sku', AttributeType::String, 'Stock Keeping Unit', 'Unique identifier for item');

        self::assertSame('sku', $attr->getCode());
        self::assertSame(AttributeType::String, $attr->getType());
        self::assertSame('Stock Keeping Unit', $attr->getName());
        self::assertSame('Unique identifier for item', $attr->getDescription());
    }

    public function testPresetAttribute(): void
    {
        $attr = new AttributeDefinition('price', AttributeType::Decimal, 'Product Price');
        $preset = new PresetAttribute($attr, isRequired: true, defaultValue: '0.00', position: 5);

        self::assertSame($attr, $preset->getAttribute());
        self::assertTrue($preset->isRequired());
        self::assertSame('0.00', $preset->getDefaultValue());
        self::assertSame(5, $preset->getPosition());
    }

    public function testEntityTypeDefinitionWithPresets(): void
    {
        $skuAttr = new AttributeDefinition('sku', AttributeType::String, 'SKU');
        $priceAttr = new AttributeDefinition('price', AttributeType::Decimal, 'Price');

        $skuPreset = new PresetAttribute($skuAttr, isRequired: true, position: 1);
        $pricePreset = new PresetAttribute($priceAttr, isRequired: false, defaultValue: '10.00', position: 2);

        $typeDef = new EntityTypeDefinition(
            'product',
            'Product Entity',
            'E-commerce product',
            presets: [$skuPreset, $pricePreset],
        );

        self::assertSame('product', $typeDef->getCode());
        self::assertSame('Product Entity', $typeDef->getName());
        self::assertSame('E-commerce product', $typeDef->getDescription());
        self::assertTrue($typeDef->hasPreset('sku'));
        self::assertTrue($typeDef->hasPreset('price'));
        self::assertFalse($typeDef->hasPreset('unknown'));
        self::assertSame($skuPreset, $typeDef->getPreset('sku'));
        self::assertCount(2, $typeDef->getPresets());

        // Immutability: adding preset creates new instance
        $weightAttr = new AttributeDefinition('weight', AttributeType::Float, 'Weight');
        $weightPreset = new PresetAttribute($weightAttr, position: 3);
        $updatedTypeDef = $typeDef->withPreset($weightPreset);

        self::assertFalse($typeDef->hasPreset('weight'));
        self::assertTrue($updatedTypeDef->hasPreset('weight'));
        self::assertCount(3, $updatedTypeDef->getPresets());

        $removedTypeDef = $updatedTypeDef->withoutPreset('sku');
        self::assertFalse($removedTypeDef->hasPreset('sku'));
        self::assertTrue($removedTypeDef->hasPreset('weight'));
    }

    public function testAttributeValue(): void
    {
        $val = new AttributeValue('price', '99.99', AttributeType::Decimal);

        self::assertSame('price', $val->getAttributeCode());
        self::assertSame('99.99', $val->getValue());
        self::assertSame(AttributeType::Decimal, $val->getType());
        self::assertSame(StorageBucket::Decimal, $val->getStorageBucket());
    }

    public function testEavEntityAttributeAccess(): void
    {
        $entity = new EavEntity('product', 101);

        self::assertSame('product', $entity->getEntityType());
        self::assertSame(101, $entity->getId());

        $entity->set('name', 'Smartphone')
            ->set('price', 899.99)
            ->set('is_active', true);

        self::assertTrue($entity->has('name'));
        self::assertSame('Smartphone', $entity->get('name'));
        self::assertSame(899.99, $entity->get('price'));
        self::assertTrue($entity->get('is_active'));
        self::assertNull($entity->get('non_existent'));
        self::assertSame('fallback', $entity->get('non_existent', 'fallback'));

        $entity->remove('is_active');
        self::assertFalse($entity->has('is_active'));

        $now = new \DateTimeImmutable('2026-09-30 12:00:00');
        $entity->setCreatedAt($now)->setUpdatedAt($now);
        self::assertSame($now, $entity->getCreatedAt());
        self::assertSame($now, $entity->getUpdatedAt());
    }
}
