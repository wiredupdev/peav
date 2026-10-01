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

namespace WireUpDev\Peav\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;
use WireUpDev\Peav\Caster\TypeCasterInterface;
use WireUpDev\Peav\EavManager;
use WireUpDev\Peav\EavManagerInterface;
use WireUpDev\Peav\Model\AttributeDefinition;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Model\PresetAttribute;
use WireUpDev\Peav\Type\AttributeType;
use WireUpDev\Peav\Type\StorageBucket;
use WireUpDev\Peav\Type\TypeInterface;

class EavManagerTest extends TestCase
{
    private \Doctrine\DBAL\Connection $connection;
    private EavManagerInterface $manager;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->manager = EavManager::create($this->connection);
    }

    public function testEndToEndLifecycleViaManager(): void
    {
        // 1. Create Schema
        $this->manager->schema()->createSchema();

        // 2. Define Attributes
        $sku = new AttributeDefinition('sku', AttributeType::String, 'Product SKU');
        $price = new AttributeDefinition('price', AttributeType::Decimal, 'Product Price');
        $inStock = new AttributeDefinition('in_stock', AttributeType::Boolean, 'Stock Availability');

        $this->manager->attributes()->saveAttribute($sku);
        $this->manager->attributes()->saveAttribute($price);
        $this->manager->attributes()->saveAttribute($inStock);

        // 3. Define Entity Type with Presets
        $productType = new EntityTypeDefinition(
            code: 'product',
            name: 'Product',
            description: 'E-commerce Product',
            presets: [
                'sku' => new PresetAttribute($sku, true, null, 1),
                'price' => new PresetAttribute($price, true, 0.0, 2),
                'in_stock' => new PresetAttribute($inStock, false, true, 3),
            ],
        );
        $this->manager->attributes()->saveEntityType($productType);

        // 4. Synchronize Flat Storage Projections
        $this->manager->flat()->syncEntityType($productType);
        $this->assertTrue($this->manager->flat()->isSynchronized($productType));

        // 5. Create and Save Entities
        $item1 = $this->manager->createEntity('product', [
            'sku' => 'PROD-101',
            'price' => 99.90,
            'in_stock' => true,
        ]);
        $this->manager->save($item1);
        $this->assertNotNull($item1->getId());

        $item2 = $this->manager->createEntity('product', [
            'sku' => 'PROD-102',
            'price' => 199.50,
            'in_stock' => false,
        ]);
        $this->manager->save($item2);
        $this->assertNotNull($item2->getId());

        // 6. Find Entity by ID
        $found = $this->manager->find('product', $item1->getId());
        $this->assertNotNull($found);
        $this->assertSame('PROD-101', $found->get('sku'));
        $this->assertSame(99.90, (float) $found->get('price'));
        $this->assertTrue((bool) $found->get('in_stock'));

        // 7. Find Multiple Entities
        $many = $this->manager->findMany('product', [$item1->getId(), $item2->getId()]);
        $this->assertCount(2, $many);

        // 8. Query via QueryBuilder
        $qb = $this->manager->createQueryBuilder('product');
        $qb->whereAttribute('price', '>', 100.0);
        $results = $qb->getEntities();

        $this->assertCount(1, $results);
        $this->assertSame('PROD-102', $results[0]->get('sku'));

        // 9. CLI Commands Accessibility
        $commands = $this->manager->commands();
        $this->assertNotEmpty($commands);
        $this->assertCount(7, $commands);

        // 10. Delete Entity
        $id1 = $item1->getId();
        $this->assertNotNull($id1);
        $this->manager->delete($item1);
        $this->assertNull($this->manager->find('product', $id1));

        $id2 = $item2->getId();
        $this->assertNotNull($id2);
        $this->manager->deleteById('product', $id2);
        $this->assertNull($this->manager->find('product', $id2));
    }

    public function testCustomCasterIntegration(): void
    {
        $this->manager->schema()->createSchema();

        $customType = new class implements TypeInterface {
            public function getName(): string
            {
                return 'currency_amount';
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

        $customCaster = new class implements TypeCasterInterface {
            public function convertToDatabaseValue(mixed $value, TypeInterface $type, AbstractPlatform $platform): ?string
            {
                if ($value === null) {
                    return null;
                }

                // If passed formatted string like "$123.45", strip "$"
                $clean = is_string($value) ? str_replace('$', '', $value) : (string) $value;

                return (string) (float) $clean;
            }

            public function convertToPHPValue(mixed $value, TypeInterface $type, AbstractPlatform $platform): ?string
            {
                if ($value === null) {
                    return null;
                }

                return '$' . number_format((float) $value, 2, '.', '');
            }
        };

        $this->manager->types()->register($customType);
        $this->manager->casters()->register('currency_amount', $customCaster);

        $amountAttr = new AttributeDefinition('amount', $customType, 'Total Amount');
        $this->manager->attributes()->saveAttribute($amountAttr);

        $orderType = new EntityTypeDefinition(
            code: 'order',
            name: 'Order',
            description: 'Customer Order',
        );
        $this->manager->attributes()->saveEntityType($orderType);

        $order = $this->manager->createEntity('order', [
            'amount' => '$250.00',
        ]);
        $this->manager->save($order);

        $orderId = $order->getId();
        $this->assertNotNull($orderId);

        $loaded = $this->manager->find('order', $orderId);
        $this->assertNotNull($loaded);
        $this->assertSame('$250.00', $loaded->get('amount'));
    }
}
