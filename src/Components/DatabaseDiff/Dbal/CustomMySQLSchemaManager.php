<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\DatabaseDiff\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\MySQLSchemaManager;

class CustomMySQLSchemaManager extends MySQLSchemaManager
{
    public function __construct(
        protected Connection $connection,
        protected AbstractPlatform $platform
    ) {
        parent::__construct($connection, $platform);
    }

    protected function _getPortableTableColumnDefinition($tableColumn): Column
    {
        $column      = parent::_getPortableTableColumnDefinition($tableColumn);
        $tableColumn = \array_change_key_case($tableColumn, \CASE_LOWER);

        $column->setPlatformOptions([
            'raw'  => $tableColumn,
        ]);

        return $column;
    }
}
