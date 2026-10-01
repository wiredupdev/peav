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
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use WireUpDev\Peav\EavManager;
use WireUpDev\Peav\Model\AttributeDefinition;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Model\PresetAttribute;
use WireUpDev\Peav\Type\AttributeType;

class LoggingTest extends TestCase
{
    private \Doctrine\DBAL\Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
    }

    public function testLogsStructuredMessagesAcrossOperations(): void
    {
        $logs = [];
        $logger = new class($logs) extends AbstractLogger {
            /** @param array<int, array{level: mixed, message: string, context: array<string, mixed>}> $logs */
            public function __construct(private array &$logs)
            {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->logs[] = [
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }

            /** @return array<int, array{level: mixed, message: string, context: array<string, mixed>}> */
            public function getCapturedLogs(): array
            {
                return $this->logs;
            }
        };

        $manager = EavManager::create(
            connection: $this->connection,
            logger: $logger,
        );

        // 1. Schema log
        $manager->schema()->createSchema();
        $this->assertNotEmpty($logs);
        $schemaLogs = array_filter($logs, fn($l) => str_contains($l['message'], 'schema') || str_contains($l['message'], 'Schema'));
        $this->assertNotEmpty($schemaLogs);

        // 2. Attribute log
        $sku = new AttributeDefinition('sku', AttributeType::String, 'Product SKU');
        $manager->attributes()->saveAttribute($sku);
        $attrLogs = array_filter($logs, fn($l) => str_contains($l['message'], 'attribute definition "sku"'));
        $this->assertNotEmpty($attrLogs);

        // 3. Entity type log
        $type = new EntityTypeDefinition('product', 'Product', presets: [
            'sku' => new PresetAttribute($sku, true, null, 1),
        ]);
        $manager->attributes()->saveEntityType($type);
        $typeLogs = array_filter($logs, fn($l) => str_contains($l['message'], 'entity type definition "product"'));
        $this->assertNotEmpty($typeLogs);

        // 4. Persistence log
        $entity = $manager->createEntity('product', ['sku' => 'LAPTOP-LOG']);
        $manager->save($entity);
        $persistLogs = array_filter($logs, fn($l) => str_contains($l['message'], 'Persisted new entity [product'));
        $this->assertNotEmpty($persistLogs);

        // 5. Query log
        $qb = $manager->createQueryBuilder('product');
        $qb->whereAttribute('sku', '=', 'LAPTOP-LOG');
        $qb->getEntities();
        $queryLogs = array_filter($logs, fn($l) => str_contains($l['message'], 'Executed EAV query for entity type "product"'));
        $this->assertNotEmpty($queryLogs);
    }
}
