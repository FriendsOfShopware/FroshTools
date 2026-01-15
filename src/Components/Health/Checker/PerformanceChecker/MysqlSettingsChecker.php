<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MysqlSettingsChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public const DOCUMENTATION_URL = 'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#mysql-configuration';

    public const MYSQL_GROUP_CONCAT_MAX_LEN = 320000;

    public const MYSQL_SQL_MODE_PART = 'ONLY_FULL_GROUP_BY';

    public const MYSQL_TIME_ZONES = [
        '+00:00',
        'UTC',
    ];

    public function __construct(
        private readonly Connection $connection,
        #[Autowire(param: 'kernel.shopware_version')]
        private readonly string $shopwareVersion,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $this->checkGroupConcatMaxLen($collection);
        $this->checkSqlMode($collection);
        $this->checkTimeZone($collection);
        $this->checkCheckDefaultEnvironmentSessionVariables($collection);
    }

    private function checkGroupConcatMaxLen(HealthCollection $collection): void
    {
        /** @var string|false $groupConcatMaxLen */
        $groupConcatMaxLen = $this->connection->fetchOne('SELECT @@group_concat_max_len');
        if (!$groupConcatMaxLen || (int) $groupConcatMaxLen < self::MYSQL_GROUP_CONCAT_MAX_LEN) {
            $collection->add(
                SettingsResult::error(
                    'sql_group_concat_max_len',
                    'MySQL value group_concat_max_len',
                    (string) $groupConcatMaxLen,
                    'min ' . self::MYSQL_GROUP_CONCAT_MAX_LEN,
                    self::DOCUMENTATION_URL,
                ),
            );
        } else {
            $collection->add(
                SettingsResult::ok(
                    'sql_group_concat_max_len',
                    'MySQL value group_concat_max_len',
                    (string) $groupConcatMaxLen,
                    'min ' . self::MYSQL_GROUP_CONCAT_MAX_LEN,
                    self::DOCUMENTATION_URL,
                ),
            );
        }
    }

    private function checkSqlMode(HealthCollection $collection): void
    {
        $sqlMode = $this->connection->fetchOne('SELECT @@sql_mode');
        if (\is_string($sqlMode) && \str_contains($sqlMode, self::MYSQL_SQL_MODE_PART)) {
            $collection->add(
                SettingsResult::error(
                    'sql_mode',
                    'MySQL value sql_mode',
                    $sqlMode,
                    'No ' . self::MYSQL_SQL_MODE_PART,
                    self::DOCUMENTATION_URL,
                ),
            );
        } else {
            $collection->add(
                SettingsResult::ok(
                    'sql_mode',
                    'MySQL value sql_mode',
                    $sqlMode,
                    'No ' . self::MYSQL_SQL_MODE_PART,
                    self::DOCUMENTATION_URL,
                ),
            );
        }
    }

    private function checkTimeZone(HealthCollection $collection): void
    {
        $timeZone = $this->connection->fetchOne('SELECT @@time_zone');
        if (\is_string($timeZone) && !\in_array($timeZone, self::MYSQL_TIME_ZONES, true)) {
            $collection->add(
                SettingsResult::warning(
                    'sql_time_zone',
                    'MySQL value time_zone',
                    $timeZone,
                    implode(', ', self::MYSQL_TIME_ZONES),
                    self::DOCUMENTATION_URL,
                ),
            );
        } else {
            $collection->add(
                SettingsResult::ok(
                    'sql_time_zone',
                    'MySQL value time_zone',
                    $timeZone,
                    implode(', ', self::MYSQL_TIME_ZONES),
                    self::DOCUMENTATION_URL,
                ),
            );
        }
    }

    private function checkCheckDefaultEnvironmentSessionVariables(HealthCollection $collection): void
    {
        // https://github.com/shopware/shopware/commit/c0a162f11852130b34e7c725bd7429aeb89a7b75
        if (\version_compare($this->shopwareVersion, '6.7.0', '>=')) {
            return;
        }

        $setSessionVariables = (bool) EnvironmentHelper::getVariable('SQL_SET_DEFAULT_SESSION_VARIABLES', true);
        if ($setSessionVariables) {
            $collection->add(
                SettingsResult::warning(
                    'sql_set_default_session_variables',
                    'MySQL session vars are set on each connect',
                    'enabled',
                    'disabled',
                    self::DOCUMENTATION_URL,
                ),
            );
        } else {
            $collection->add(
                SettingsResult::ok(
                    'sql_set_default_session_variables',
                    'MySQL session vars are set on each connect',
                    'disabled',
                    'disabled',
                    self::DOCUMENTATION_URL,
                ),
            );
        }
    }
}
