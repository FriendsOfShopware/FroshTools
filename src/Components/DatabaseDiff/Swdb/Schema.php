<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\DatabaseDiff\Swdb;

use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
class Schema extends Struct
{
    /**
     * @param string $version
     * @param array<Table> $tables
     */
    public function __construct(
        public readonly string $version,
        public readonly array  $tables = [],
    ) {
    }

    public static function createFromData(array $data, string $version): static
    {
        return new static(
            $version,
            \array_map(Table::createFromData(...), $data),
        );
    }
}
