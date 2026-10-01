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

namespace WireUpDev\Peav\Tests\Unit\Flat;

use PHPUnit\Framework\TestCase;
use WireUpDev\Peav\Flat\FlatDocumentSerializer;
use WireUpDev\Peav\Model\AttributeDefinition;
use WireUpDev\Peav\Model\EavEntity;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Model\PresetAttribute;
use WireUpDev\Peav\Type\AttributeType;

final class FlatDocumentSerializerTest extends TestCase
{
    public function testSerializesEntityToDocumentArrayAndJson(): void
    {
        $serializer = new FlatDocumentSerializer();

        $sku = new AttributeDefinition('sku', AttributeType::String, 'SKU');
        $price = new AttributeDefinition('price', AttributeType::Decimal, 'Price');
        $tags = new AttributeDefinition('tags', AttributeType::Json, 'Tags');

        $entityType = new EntityTypeDefinition('product', 'Product Entity', presets: [
            new PresetAttribute($sku, isRequired: true),
            new PresetAttribute($price, defaultValue: '0.00'),
            new PresetAttribute($tags),
        ]);

        $entity = new EavEntity('product', 42, [
            'sku' => 'PROD-42',
            'price' => 199.99,
            'tags' => ['electronics', 'gadget'],
        ]);
        $now = new \DateTimeImmutable('2026-09-30 10:00:00');
        $entity->setCreatedAt($now)->setUpdatedAt($now);

        $arrayDoc = $serializer->toArray($entity, $entityType);

        self::assertSame(42, $arrayDoc['entity_id']);
        self::assertSame('product', $arrayDoc['entity_type']);
        self::assertSame('2026-09-30T10:00:00+00:00', $arrayDoc['created_at']);
        self::assertSame('PROD-42', $arrayDoc['sku']);
        self::assertSame(199.99, $arrayDoc['price']);
        self::assertSame(['electronics', 'gadget'], $arrayDoc['tags']);

        $jsonDoc = $serializer->toJson($entity, $entityType);
        self::assertJson($jsonDoc);
        self::assertStringContainsString('PROD-42', $jsonDoc);
    }
}
