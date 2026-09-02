<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\DatabaseDiff\Swdb;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class InverseTableRelation extends TableRelation
{
    public function type(): string
    {
        return 'relationsToTable';
    }

    public function key(): string
    {
        return \sprintf('%s.%s', $this->foreignTable, $this->foreignField);
    }

    public function value(): string
    {
        return "foreign key `{$this->foreignTable}.{$this->foreignField}` references `{$this->table}.{$this->localField}`";
    }
}
