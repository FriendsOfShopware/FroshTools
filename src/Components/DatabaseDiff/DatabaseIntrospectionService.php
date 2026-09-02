<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\DatabaseDiff;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Schema as Dbal;
use Doctrine\DBAL\Types\DateTimeType;
use Frosh\Tools\Components\DatabaseDiff\Swdb\Schema;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DatabaseIntrospectionService
{
    public function __construct(
        #[Autowire(param: 'kernel.shopware_version')]
        private readonly string $shopwareVersion,
        private readonly Connection $connection,
    ) {
    }

    public function getDatabaseSchema(): Schema
    {
        return new Schema($this->shopwareVersion, $this->introspectSchema());
    }

    /**
     * @return array<string, Swdb\Table>
     */
    private function introspectSchema(): array
    {
        $schemaManager = $this->connection->createSchemaManager();

        try {
            $tables = \method_exists($schemaManager, 'introspectTables')
                ? $schemaManager->introspectTables()
                : $schemaManager->listTables();
        } catch (DbalException $e) {
            throw new \RuntimeException('DBAL introspection error', previous: $e);
        }
        $result = [];

        foreach ($tables as $table) {
            $result[$table->getName()] = $this->introspectTable($table);
        }

        return $result;
    }

    private function introspectTable(Dbal\Table $table): Swdb\Table
    {
        [$relationsFromTable, $relationsToTable] = $this->introspectTableRelations($table);

        $fields  = \array_filter(
            \array_map(fn ($column) => $this->introspectTableField($table, $column),
                $table->getColumns()
            )
        );
        $indexes = \array_filter(
            \array_map(fn ($index)  => $this->introspectIndex($table, $index),
                $table->getIndexes()
            )
        );

        return new Swdb\Table(
            $table->getName(),
            $fields,
            $relationsFromTable,
            $relationsToTable,
            $indexes,
        );
    }

    private function introspectTableField(Dbal\Table $table, Dbal\Column $column): ?Swdb\TableField
    {
        $type = $column->getType();

        $typeDeclaration = $type->getSQLDeclaration([
            'length'    => $column->getLength(),
            'fixed'     => $column->getFixed(),
            'scale'     => $column->getScale(),
            'precision' => $column->getPrecision(),
            'unsigned'  => $column->getUnsigned(),
        ], $this->connection->getDatabasePlatform());

        if ($type instanceof DateTimeType) {
            $typeDeclaration = \sprintf('%s(%d)', $typeDeclaration, $column->getLength());
        }

        $tableField = new Swdb\TableField(
            $column->getName(),
            \strtolower($typeDeclaration),
            !$column->getNotnull(),
            $this->introspectTableFieldKey($table, $column),
            $column->getDefault(),
            \implode(' ', \array_filter([
                $column->getAutoincrement() ? 'auto_increment' : '',
                $column->getColumnDefinition() ?? '',
            ])),
        );
        $tableField->table = $table->getName();

        return $tableField;
    }

    private function introspectTableFieldKey(Dbal\Table $table, Dbal\Column $column): string
    {
        foreach ($table->getIndexes() as $index) {
            if (!\in_array($column->getName(), $index->getColumns(), true)) {
                continue;
            }

            if ($index->isPrimary()) {
                return 'PRI';
            }

            if ($index->isUnique()) {
                return 'UNI';
            }

            return 'MUL';
        }

        return '';
    }

    /**
     * @see https://github.com/llorentegerman/mysql-json-schema/blob/master/src/mysql.js
     *
     * @return array{0: array, 1: array}
     */
    private function introspectTableRelations(Dbal\Table $table): array
    {
        $resultFrom = $this->connection->executeQuery(
            <<<'SQL'
-- mysql-json-schema relations from table
SELECT  TABLE_SCHEMA            as localSchema,
        TABLE_NAME              as localTable,
        COLUMN_NAME             as localField,
        REFERENCED_TABLE_SCHEMA as foreignSchema,
        REFERENCED_TABLE_NAME   as foreignTable,
        REFERENCED_COLUMN_NAME  as foreignField
FROM
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE
    TABLE_SCHEMA = SCHEMA()
  AND REFERENCED_TABLE_NAME IS NOT NULL
  and (TABLE_NAME = :table);
SQL,
            [
                'table' => $table->getName(),
            ],
        );
        $resultTo   = $this->connection->executeQuery(
            <<<'SQL'
-- mysql-json-schema relations to table
SELECT  TABLE_SCHEMA            as foreignSchema,
        TABLE_NAME              as foreignTable,
        COLUMN_NAME             as foreignField,
        REFERENCED_TABLE_SCHEMA as localSchema,
        REFERENCED_TABLE_NAME   as localTable,
        REFERENCED_COLUMN_NAME  as localField
FROM
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE
    TABLE_SCHEMA = SCHEMA()
  AND REFERENCED_TABLE_NAME IS NOT NULL
  and (REFERENCED_TABLE_NAME = :table);
SQL,
            [
                'table' => $table->getName(),
            ],
        );

        return [
            \array_map(fn ($data) => $this->introspectTableRelationFrom($table, $data),
                $resultFrom->fetchAllAssociative()
            ),
            \array_map(fn ($data) => $this->introspectTableRelationTo($table, $data),
                $resultTo->fetchAllAssociative()
            ),
        ];
    }

    private function introspectIndex(Dbal\Table $table, Dbal\Index $index): ?Swdb\TableIndex
    {
        // Exclude automatic indexes.
        if (\str_starts_with($index->getName(), 'IDX_')) {
            return null;
        }

        $tableIndex = new Swdb\TableIndex(
            \implode(',', $index->getColumns()),
            $index->getName(),
            $index->isUnique(),
        );
        $tableIndex->table = $table->getName();

        return $tableIndex;
    }

    private function introspectTableRelationFrom(Dbal\Table $table, array $data): Swdb\TableRelation
    {
        $tableRelation = Swdb\TableRelation::createFromData($data);
        $tableRelation->table = $table->getName();

        return $tableRelation;
    }

    private function introspectTableRelationTo(Dbal\Table $table, array $data): Swdb\InverseTableRelation
    {
        $tableRelation = Swdb\InverseTableRelation::createFromData($data);
        $tableRelation->table = $table->getName();

        return $tableRelation;
    }
}
