<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class MysqlTimeZoneChecker implements HealthCheckerInterface, CheckerInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function collect(HealthCollection $collection): void
    {
        $snippet = 'MySQL Time Zone Support';

        try {
            $result = $this->connection->fetchOne('SELECT CONVERT_TZ(\'2024-01-01 00:00:00\', \'UTC\', \'Europe/Berlin\')');
        } catch (\Throwable) {
            $collection->add(SettingsResult::warning('mysql-timezone', $snippet, 'Query failed', 'Time zone tables available'));

            return;
        }

        if ($result === null) {
            $collection->add(SettingsResult::warning(
                'mysql-timezone',
                $snippet,
                'Time zone tables not available',
                'Time zone tables available',
                'https://dev.mysql.com/doc/refman/8.4/en/mysql-tzinfo-to-sql.html',
            ));

            return;
        }

        $collection->add(SettingsResult::ok('mysql-timezone', $snippet, 'available', 'available'));
    }
}
