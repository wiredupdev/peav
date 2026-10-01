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

namespace WireUpDev\Peav\Event;

/**
 * Dispatched after database schema migration DDL statements have been executed.
 */
final readonly class SchemaMigratedEvent
{
    /**
     * @param list<string> $queries The SQL DDL queries executed
     */
    public function __construct(
        private array $queries,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }
}
