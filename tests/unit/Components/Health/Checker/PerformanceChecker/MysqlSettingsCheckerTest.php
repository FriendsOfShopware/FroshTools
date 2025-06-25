<?php

namespace Frosh\Tools\Tests\unit\Components\Health\Checker\PerformanceChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\PerformanceChecker\MysqlSettingsChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Test\SettingsResultAssertionHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MysqlSettingsChecker::class)]
class MysqlSettingsCheckerTest extends TestCase
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function groupConcatMaxLenProvider(): array
    {
        return [
            'group_concat_max_len too small' => [
                'groupConcatMaxLen' => '1000',
                'expectError' => true,
            ],
            'group_concat_max_len exactly minimum' => [
                'groupConcatMaxLen' => (string) MysqlSettingsChecker::MYSQL_GROUP_CONCAT_MAX_LEN,
                'expectError' => false,
            ],
            'group_concat_max_len above minimum' => [
                'groupConcatMaxLen' => (string) (MysqlSettingsChecker::MYSQL_GROUP_CONCAT_MAX_LEN + 1000),
                'expectError' => false,
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function sqlModeProvider(): array
    {
        return [
            'sql_mode contains ONLY_FULL_GROUP_BY' => [
                'sqlMode' => 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,ONLY_FULL_GROUP_BY',
                'expectError' => true,
            ],
            'sql_mode without ONLY_FULL_GROUP_BY' => [
                'sqlMode' => 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION',
                'expectError' => false,
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function timeZoneProvider(): array
    {
        return [
            'time_zone is UTC' => [
                'timeZone' => 'UTC',
                'expectWarning' => false,
            ],
            'time_zone is +00:00' => [
                'timeZone' => '+00:00',
                'expectWarning' => false,
            ],
            'time_zone is different' => [
                'timeZone' => 'Europe/Berlin',
                'expectWarning' => true,
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function sessionVariablesProvider(): array
    {
        return [
            'session variables setting enabled' => [
                'setSessionVariables' => true,
                'expectWarning' => true,
            ],
            'session variables setting disabled' => [
                'setSessionVariables' => false,
                'expectWarning' => false,
            ],
            'session variables setting null (defaults to true)' => [
                'setSessionVariables' => null,
                'expectWarning' => true,
            ],
        ];
    }

    #[DataProvider('groupConcatMaxLenProvider')]
    public function testCheckGroupConcatMaxLen(string $groupConcatMaxLen, bool $expectError): void
    {
        // Create a mock connection
        $connection = $this->createMock(Connection::class);
        
        // Configure the mock to return our test value
        $connection->method('fetchOne')
            ->willReturnMap([
                ['SELECT @@group_concat_max_len', [], $groupConcatMaxLen],
                ['SELECT @@sql_mode', [], ''],
                ['SELECT @@time_zone', [], 'UTC'],
            ]);

        // Create the checker with our test configuration
        $checker = new MysqlSettingsChecker($connection, false);

        // Create a health collection
        $collection = new HealthCollection();

        // Call the collect method
        $checker->collect($collection);

        if ($expectError) {
            // Assert that an error message was added to the collection
            SettingsResultAssertionHelper::assertSettingsResultMatches(
                $collection,
                'sql_group_concat_max_len',
                SettingsResult::ERROR,
                $groupConcatMaxLen,
                'min ' . MysqlSettingsChecker::MYSQL_GROUP_CONCAT_MAX_LEN,
                MysqlSettingsChecker::DOCUMENTATION_URL
            );
        } else {
            // Assert that no error message for group_concat_max_len was added
            SettingsResultAssertionHelper::assertSettingsResultNotExists($collection, 'sql_group_concat_max_len');
        }
    }

    #[DataProvider('sqlModeProvider')]
    public function testCheckSqlMode(string $sqlMode, bool $expectError): void
    {
        // Create a mock connection
        $connection = $this->createMock(Connection::class);
        
        // Configure the mock to return our test value
        $connection->method('fetchOne')
            ->willReturnMap([
                ['SELECT @@group_concat_max_len', [], (string) (MysqlSettingsChecker::MYSQL_GROUP_CONCAT_MAX_LEN + 1000)],
                ['SELECT @@sql_mode', [], $sqlMode],
                ['SELECT @@time_zone', [], 'UTC'],
            ]);

        // Create the checker with our test configuration
        $checker = new MysqlSettingsChecker($connection, false);

        // Create a health collection
        $collection = new HealthCollection();

        // Call the collect method
        $checker->collect($collection);

        if ($expectError) {
            // Assert that an error message was added to the collection
            SettingsResultAssertionHelper::assertSettingsResultMatches(
                $collection,
                'sql_mode',
                SettingsResult::ERROR,
                $sqlMode,
                'No ' . MysqlSettingsChecker::MYSQL_SQL_MODE_PART,
                MysqlSettingsChecker::DOCUMENTATION_URL
            );
        } else {
            // Assert that no error message for sql_mode was added
            SettingsResultAssertionHelper::assertSettingsResultNotExists($collection, 'sql_mode');
        }
    }

    #[DataProvider('timeZoneProvider')]
    public function testCheckTimeZone(string $timeZone, bool $expectWarning): void
    {
        // Create a mock connection
        $connection = $this->createMock(Connection::class);
        
        // Configure the mock to return our test value
        $connection->method('fetchOne')
            ->willReturnMap([
                ['SELECT @@group_concat_max_len', [], (string) (MysqlSettingsChecker::MYSQL_GROUP_CONCAT_MAX_LEN + 1000)],
                ['SELECT @@sql_mode', [], 'STRICT_TRANS_TABLES'],
                ['SELECT @@time_zone', [], $timeZone],
            ]);

        // Create the checker with our test configuration
        $checker = new MysqlSettingsChecker($connection, false);

        // Create a health collection
        $collection = new HealthCollection();

        // Call the collect method
        $checker->collect($collection);

        if ($expectWarning) {
            // Assert that a warning message was added to the collection
            SettingsResultAssertionHelper::assertSettingsResultMatches(
                $collection,
                'sql_time_zone',
                SettingsResult::WARNING,
                $timeZone,
                implode(', ', MysqlSettingsChecker::MYSQL_TIME_ZONES),
                MysqlSettingsChecker::DOCUMENTATION_URL
            );
        } else {
            // Assert that no warning message for time_zone was added
            SettingsResultAssertionHelper::assertSettingsResultNotExists($collection, 'sql_time_zone');
        }
    }

    #[DataProvider('sessionVariablesProvider')]
    public function testCheckSessionVariables(?bool $setSessionVariables, bool $expectWarning): void
    {
        // Create a mock connection
        $connection = $this->createMock(Connection::class);
        
        // Configure the mock to return values that won't trigger other warnings
        $connection->method('fetchOne')
            ->willReturnMap([
                ['SELECT @@group_concat_max_len', [], (string) (MysqlSettingsChecker::MYSQL_GROUP_CONCAT_MAX_LEN + 1000)],
                ['SELECT @@sql_mode', [], 'STRICT_TRANS_TABLES'],
                ['SELECT @@time_zone', [], 'UTC'],
            ]);

        // Create the checker with our test configuration
        $checker = new MysqlSettingsChecker($connection, $setSessionVariables);

        // Create a health collection
        $collection = new HealthCollection();

        // Call the collect method
        $checker->collect($collection);

        if ($expectWarning) {
            // Assert that a warning message was added to the collection
            SettingsResultAssertionHelper::assertSettingsResultMatches(
                $collection,
                'sql_set_default_session_variables',
                SettingsResult::WARNING,
                'enabled',
                'disabled',
                MysqlSettingsChecker::DOCUMENTATION_URL
            );
        } else {
            // Assert that no warning message for session variables was added
            SettingsResultAssertionHelper::assertSettingsResultNotExists($collection, 'sql_set_default_session_variables');
        }
    }

    public function testCollectAllChecks(): void
    {
        // Create a mock connection
        $connection = $this->createMock(Connection::class);
        
        // Configure the mock to return values that will trigger all warnings/errors
        $connection->method('fetchOne')
            ->willReturnMap([
                ['SELECT @@group_concat_max_len', [], '1000'],
                ['SELECT @@sql_mode', [], 'STRICT_TRANS_TABLES,ONLY_FULL_GROUP_BY'],
                ['SELECT @@time_zone', [], 'Europe/Berlin'],
            ]);

        // Create the checker with our test configuration
        $checker = new MysqlSettingsChecker($connection, true);

        // Create a health collection
        $collection = new HealthCollection();

        // Call the collect method
        $checker->collect($collection);

        // Assert that all 4 messages were added to the collection
        SettingsResultAssertionHelper::assertSettingsResultCount($collection, 4);
        
        // Assert each specific message
        SettingsResultAssertionHelper::assertSettingsResultMatches(
            $collection,
            'sql_group_concat_max_len',
            SettingsResult::ERROR,
            '1000',
            'min ' . MysqlSettingsChecker::MYSQL_GROUP_CONCAT_MAX_LEN,
            MysqlSettingsChecker::DOCUMENTATION_URL
        );
        
        SettingsResultAssertionHelper::assertSettingsResultMatches(
            $collection,
            'sql_mode',
            SettingsResult::ERROR,
            'STRICT_TRANS_TABLES,ONLY_FULL_GROUP_BY',
            'No ' . MysqlSettingsChecker::MYSQL_SQL_MODE_PART,
            MysqlSettingsChecker::DOCUMENTATION_URL
        );
        
        SettingsResultAssertionHelper::assertSettingsResultMatches(
            $collection,
            'sql_time_zone',
            SettingsResult::WARNING,
            'Europe/Berlin',
            implode(', ', MysqlSettingsChecker::MYSQL_TIME_ZONES),
            MysqlSettingsChecker::DOCUMENTATION_URL
        );
        
        SettingsResultAssertionHelper::assertSettingsResultMatches(
            $collection,
            'sql_set_default_session_variables',
            SettingsResult::WARNING,
            'enabled',
            'disabled',
            MysqlSettingsChecker::DOCUMENTATION_URL
        );
    }
}
