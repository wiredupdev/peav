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

namespace WireUpDev\Peav\Type;

/**
 * Storage bucket classification determining the physical value table.
 */
enum StorageBucket: string
{
    case String = 'string';
    case Integer = 'int';
    case Decimal = 'decimal';
    case DateTime = 'datetime';
    case Boolean = 'bool';
    case Text = 'text';
    case Json = 'json';
    case Blob = 'blob';

    /**
     * Returns the default suffix for value tables.
     */
    public function getTableSuffix(): string
    {
        return $this->value;
    }
}
