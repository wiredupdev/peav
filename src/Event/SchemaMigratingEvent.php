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
 * Dispatched before database schema migration DDL statements are executed.
 */
final readonly class SchemaMigratingEvent
{
    /**
     * @param list<string> $queries The SQL DDL queries to be executed
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
