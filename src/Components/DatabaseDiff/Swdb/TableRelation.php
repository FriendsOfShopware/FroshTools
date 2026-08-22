<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\DatabaseDiff\Swdb;

use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class TableRelation extends Struct implements TableMemberInterface
{
    public string $table;

    public function __construct(
        public readonly string $localField,
        public readonly string $foreignSchema,
        public readonly string $foreignTable,
        public readonly string $foreignField,
    ) {
    }

    public function type(): string
    {
        return 'relationsFromTable';
    }

    public function key(): string
    {
        return \sprintf('%s.%s', $this->table, $this->localField);
    }

    public function label(): string
    {
        return "foreign key `{$this->table}.{$this->localField}` references `{$this->foreignTable}.{$this->foreignField}`";
    }

    public function compare($member): bool
    {
        return $this != $member;
    }

    public static function createFromData(array $data): static
    {
        return new static(
            $data['localField'],
            $data['foreignSchema'],
            $data['foreignTable'],
            $data['foreignField'],
        );
    }
}
