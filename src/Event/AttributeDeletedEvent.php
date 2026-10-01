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

use WireUpDev\Peav\Model\AttributeDefinition;

/**
 * Dispatched after an unused attribute definition has been safely deleted.
 */
final readonly class AttributeDeletedEvent
{
    public function __construct(
        private AttributeDefinition $attribute,
    ) {
    }

    public function getAttribute(): AttributeDefinition
    {
        return $this->attribute;
    }
}
