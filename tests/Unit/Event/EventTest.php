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

namespace WireUpDev\Peav\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\StoppableEventInterface;
use WireUpDev\Peav\Event\AttributeDeletedEvent;
use WireUpDev\Peav\Event\AttributeSavedEvent;
use WireUpDev\Peav\Event\EntityCreatedEvent;
use WireUpDev\Peav\Event\EntityCreatingEvent;
use WireUpDev\Peav\Event\EntityDeletedEvent;
use WireUpDev\Peav\Event\EntityDeletingEvent;
use WireUpDev\Peav\Event\EntityLoadedEvent;
use WireUpDev\Peav\Event\EntityTypePresetUpdatedEvent;
use WireUpDev\Peav\Event\EntityUpdatedEvent;
use WireUpDev\Peav\Event\EntityUpdatingEvent;
use WireUpDev\Peav\Event\FlatTableReindexedEvent;
use WireUpDev\Peav\Event\FlatTableReindexingEvent;
use WireUpDev\Peav\Event\SchemaMigratedEvent;
use WireUpDev\Peav\Event\SchemaMigratingEvent;
use WireUpDev\Peav\Model\AttributeDefinition;
use WireUpDev\Peav\Model\EavEntity;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Type\AttributeType;

final class EventTest extends TestCase
{
    public function testStoppableEntityEvents(): void
    {
        $entity = new EavEntity('product', 1);

        $creating = new EntityCreatingEvent($entity);
        self::assertInstanceOf(StoppableEventInterface::class, $creating);
        self::assertSame($entity, $creating->getEntity());
        self::assertFalse($creating->isPropagationStopped());
        $creating->stopPropagation();
        self::assertTrue($creating->isPropagationStopped());

        $updating = new EntityUpdatingEvent($entity);
        self::assertInstanceOf(StoppableEventInterface::class, $updating);
        self::assertSame($entity, $updating->getEntity());
        self::assertFalse($updating->isPropagationStopped());
        $updating->stopPropagation();
        self::assertTrue($updating->isPropagationStopped());

        $deleting = new EntityDeletingEvent($entity);
        self::assertInstanceOf(StoppableEventInterface::class, $deleting);
        self::assertSame($entity, $deleting->getEntity());
        self::assertFalse($deleting->isPropagationStopped());
        $deleting->stopPropagation();
        self::assertTrue($deleting->isPropagationStopped());
    }

    public function testCompletedEntityEvents(): void
    {
        $entity = new EavEntity('product', 1);

        $created = new EntityCreatedEvent($entity);
        self::assertSame($entity, $created->getEntity());

        $updated = new EntityUpdatedEvent($entity);
        self::assertSame($entity, $updated->getEntity());

        $deleted = new EntityDeletedEvent($entity);
        self::assertSame($entity, $deleted->getEntity());

        $loaded = new EntityLoadedEvent($entity);
        self::assertSame($entity, $loaded->getEntity());
    }

    public function testAttributeAndPresetEvents(): void
    {
        $attr = new AttributeDefinition('sku', AttributeType::String, 'SKU Code');
        $saved = new AttributeSavedEvent($attr);
        self::assertSame($attr, $saved->getAttribute());

        $deleted = new AttributeDeletedEvent($attr);
        self::assertSame($attr, $deleted->getAttribute());

        $typeDef = new EntityTypeDefinition('product', 'Product Entity');
        $presetEvent = new EntityTypePresetUpdatedEvent($typeDef);
        self::assertSame($typeDef, $presetEvent->getEntityType());
    }

    public function testSchemaEvents(): void
    {
        $queries = ['CREATE TABLE test (id INT)'];

        $migrating = new SchemaMigratingEvent($queries);
        self::assertSame($queries, $migrating->getQueries());

        $migrated = new SchemaMigratedEvent($queries);
        self::assertSame($queries, $migrated->getQueries());
    }

    public function testFlatReindexEvents(): void
    {
        $reindexing = new FlatTableReindexingEvent('product', 500);
        self::assertSame('product', $reindexing->getEntityType());
        self::assertSame(500, $reindexing->getBatchSize());

        $reindexed = new FlatTableReindexedEvent('product', 1250);
        self::assertSame('product', $reindexed->getEntityType());
        self::assertSame(1250, $reindexed->getTotalIndexed());
    }
}
