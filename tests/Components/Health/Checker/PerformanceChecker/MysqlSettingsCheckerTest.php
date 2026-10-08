<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\PerformanceChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\PerformanceChecker\MysqlSettingsChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MysqlSettingsChecker::class)]
class MysqlSettingsCheckerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function utcTimeZoneProvider(): iterable
    {
        yield 'offset' => ['+00:00', 'CEST'];
        yield 'named UTC' => ['UTC', 'CEST'];
        yield 'named Etc/UTC' => ['Etc/UTC', 'CEST'];
        yield 'system in UTC' => ['SYSTEM', 'UTC'];
    }

    #[DataProvider('utcTimeZoneProvider')]
    public function testDoesNotWarnWhenTimeZoneIsUtc(string $timeZone, string $systemTimeZone): void
    {
        $result = $this->collectTimeZoneResult('6.6.10.0', $timeZone, $systemTimeZone);

        static::assertNull($result);
    }

    public function testWarnsWhenTimeZoneIsNotUtc(): void
    {
        $result = $this->collectTimeZoneResult('6.6.10.0', 'Europe/Berlin', 'UTC');

        static::assertNotNull($result);
        static::assertSame(SettingsResult::WARNING, $result->state);
        static::assertSame('Europe/Berlin', $result->current);
        static::assertSame(implode(', ', MysqlSettingsChecker::MYSQL_TIME_ZONES), $result->recommended);
    }

    public function testWarnsWithSystemTimeZoneWhenSystemIsNotUtc(): void
    {
        $result = $this->collectTimeZoneResult('6.6.10.0', 'SYSTEM', 'CEST');

        static::assertNotNull($result);
        static::assertSame(SettingsResult::WARNING, $result->state);
        static::assertSame('SYSTEM (CEST)', $result->current);
    }

    public function testSkipsTimeZoneCheckOnShopware67(): void
    {
        $result = $this->collectTimeZoneResult('6.7.0.0', 'SYSTEM', 'CEST', false);

        static::assertNull($result);
    }

    private function collectTimeZoneResult(
        string $shopwareVersion,
        string $timeZone,
        string $systemTimeZone,
        bool $expectQuery = true,
    ): ?SettingsResult {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnMap([
            ['SELECT @@group_concat_max_len', [], [], (string) MysqlSettingsChecker::MYSQL_GROUP_CONCAT_MAX_LEN],
            ['SELECT @@sql_mode', [], [], ''],
        ]);
        $connection->expects($expectQuery ? static::once() : static::never())
            ->method('fetchAssociative')
            ->willReturn(['time_zone' => $timeZone, 'system_time_zone' => $systemTimeZone]);

        $collection = new HealthCollection();
        (new MysqlSettingsChecker($connection, $shopwareVersion))->collect($collection);

        foreach ($collection as $result) {
            if ($result->id === 'sql_time_zone') {
                return $result;
            }
        }

        return null;
    }
}
