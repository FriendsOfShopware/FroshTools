<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\DatabaseDiff\Swdb;

/**
 * @property string $table
 */
interface TableMemberInterface
{
    public function type(): string;

    public function key(): string;

    public function value(): string;

    /**
     * @param static $member
     */
    public function compare($member): bool;
}
