<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\DatabaseDiff\Swdb;

use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class TableIndex extends Struct implements tableMemberInterface
{
    public string $table;

    public function __construct(
        public readonly string $columns,
        public readonly string $index,
        public readonly bool $unique,
    ) {
    }

    public function type(): string
    {
        return 'indexes';
    }

    public function key(): string
    {
        return $this->index;
    }

    public function label(): string
    {
        return \implode(' ', [
            "/* index={$this->index} */",
            "{$this->table}($this->columns)",
            $this->unique ? 'unique' : '',
        ]);
    }

    public function compare($member): bool
    {
        return $this != $member;
    }

    public static function createFromData(array $data): static
    {
        return new static(
            $data['Columns'],
            $data['Index'],
            (bool)$data['Unique'],
        );
    }
}
