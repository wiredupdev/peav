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
 * Interface contract for EAV attribute types.
 */
interface TypeInterface
{
    /**
     * Unique name identifying this attribute type.
     */
    public function getName(): string;

    /**
     * The underlying physical storage bucket for persisting values.
     */
    public function getStorageBucket(): StorageBucket;

    /**
     * The Doctrine DBAL type name associated with this attribute type.
     */
    public function getDbalTypeName(): string;
}
