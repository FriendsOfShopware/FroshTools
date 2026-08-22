<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\DatabaseDiff\Swdb;

use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class Table extends Struct
{
    /**
     * @param array<TableField>    $fields
     * @param array<TableRelation> $relationsFromTable
     * @param array<TableRelation> $relationsToTable
     * @param array<TableIndex>    $indexes
     */
    public function __construct(
        public readonly string $table,
        public readonly array  $fields             = [],
        public readonly array  $relationsFromTable = [],
        public readonly array  $relationsToTable   = [],
        public readonly array  $indexes            = [],
    ) {
    }

    public static function createFromData(array $data): static
    {
        $table = new static(
            $data['table'],
            \array_map(TableField::createFromData(...), $data['fields']),
            \array_map(TableRelation::createFromData(...), $data['relationsFromTable']),
            \array_map(InverseTableRelation::createFromData(...), $data['relationsToTable']),
            \array_map(TableIndex::createFromData(...), $data['indexes']),
        );

        $vars = $table->getVars();
        foreach ($vars as $list) {
            if (!\is_array($list)) {
                continue;
            }

            foreach ($list as $entry) {
                /**
                 * @var TableField|TableRelation|InverseTableRelation|TableIndex $entry
                 */
                $entry->table = $table->table;
            }
        }

        return $table;
    }
}
