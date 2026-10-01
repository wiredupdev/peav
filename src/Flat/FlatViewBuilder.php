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

use WireUpDev\Peav\Model\EntityTypeDefinition;
use WireUpDev\Peav\Schema\TableConfig;

/**
 * Builds dynamic SQL CREATE VIEW and DROP VIEW DDL statements for virtual flat projections.
 */
class FlatViewBuilder
{
    public function __construct(
        private readonly TableConfig $tableConfig = new TableConfig(),
        private readonly FlatConfig $flatConfig = new FlatConfig(),
    ) {
    }

    /**
     * Generates a CREATE VIEW SQL statement aggregating preset attributes into a virtual flat table.
     */
    public function buildCreateViewSql(EntityTypeDefinition $entityType): string
    {
        $viewName = $this->tableConfig->getFlatViewName($entityType->getCode());
        $entitiesTable = $this->tableConfig->getEntitiesTable();
        $entityTypesTable = $this->tableConfig->getEntityTypesTable();
        $attributesTable = $this->tableConfig->getAttributesTable();
        $typeCode = $entityType->getCode();

        $selectColumns = [
            'e.id AS entity_id',
            'e.created_at',
            'e.updated_at',
        ];

        $joins = [];
        $joins[] = sprintf(
            'INNER JOIN %s et ON et.id = e.entity_type_id AND et.code = \'%s\'',
            $entityTypesTable,
            addslashes($typeCode),
        );

        foreach ($entityType->getPresets() as $preset) {
            $attribute = $preset->getAttribute();
            $code = $attribute->getCode();
            $bucket = $attribute->getType()->getStorageBucket();
            $valueTable = $this->tableConfig->getValueTableName($bucket);

            $attrAlias = sprintf('attr_%s', $code);
            $valAlias = sprintf('val_%s', $code);

            $selectColumns[] = sprintf('%s.value AS %s', $valAlias, $code);

            $joins[] = sprintf(
                'LEFT JOIN %s %s ON %s.code = \'%s\'',
                $attributesTable,
                $attrAlias,
                $attrAlias,
                addslashes($code),
            );

            $joins[] = sprintf(
                'LEFT JOIN %s %s ON %s.entity_id = e.id AND %s.attribute_id = %s.id',
                $valueTable,
                $valAlias,
                $valAlias,
                $valAlias,
                $attrAlias,
            );
        }

        return sprintf(
            "CREATE VIEW %s AS\nSELECT\n  %s\nFROM %s e\n%s",
            $viewName,
            implode(",\n  ", $selectColumns),
            $entitiesTable,
            implode("\n", $joins),
        );
    }

    /**
     * Generates a DROP VIEW IF EXISTS SQL statement.
     */
    public function buildDropViewSql(EntityTypeDefinition $entityType): string
    {
        $viewName = $this->tableConfig->getFlatViewName($entityType->getCode());

        return sprintf('DROP VIEW IF EXISTS %s', $viewName);
    }

    public function getTableConfig(): TableConfig
    {
        return $this->tableConfig;
    }

    public function getFlatConfig(): FlatConfig
    {
        return $this->flatConfig;
    }
}
