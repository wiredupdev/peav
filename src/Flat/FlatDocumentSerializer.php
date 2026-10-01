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

namespace WireUpDev\Peav\Flat;

use WireUpDev\Peav\Model\EavEntity;
use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Type\StorageBucket;

/**
 * Normalizes entities and their preset attributes into flat key-value arrays and JSON documents for non-SQL sinks.
 */
class FlatDocumentSerializer implements FlatDocumentSerializerInterface
{
    public function toArray(EavEntity $entity, EntityTypeDefinition $entityType): array
    {
        $now = new \DateTimeImmutable();
        $createdAt = $entity->getCreatedAt() ?? $now;
        $updatedAt = $entity->getUpdatedAt();

        $doc = [
            'entity_id' => $entity->getId(),
            'entity_type' => $entity->getEntityType(),
            'created_at' => $createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $updatedAt?->format(\DateTimeInterface::ATOM),
        ];

        foreach ($entityType->getPresets() as $preset) {
            $code = $preset->getAttributeCode();
            $val = $entity->get($code, $preset->getDefaultValue());

            if ($val instanceof \DateTimeInterface) {
                $val = $val->format(\DateTimeInterface::ATOM);
            }

            $doc[$code] = $val;
        }

        // Include any additional dynamic attributes present on the entity
        foreach ($entity->all() as $attrCode => $attrVal) {
            if (!isset($doc[$attrCode])) {
                if ($attrVal instanceof \DateTimeInterface) {
                    $attrVal = $attrVal->format(\DateTimeInterface::ATOM);
                }
                $doc[$attrCode] = $attrVal;
            }
        }

        return $doc;
    }

    public function toJson(EavEntity $entity, EntityTypeDefinition $entityType): string
    {
        return json_encode($this->toArray($entity, $entityType), JSON_THROW_ON_ERROR);
    }
}
