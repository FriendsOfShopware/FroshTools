<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Checker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\Checker\StaleTokenCleanupChecker;
use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Symfony\Component\Clock\MockClock;

#[CoversClass(StaleTokenCleanupChecker::class)]
class StaleTokenCleanupCheckerTest extends TestCase
{
    private const NOW = '2023-01-10 12:00:00';
    private const EXPIRE_DAYS = 30;

    public function testReportsOkWhenNothingIsStale(): void
    {
        $findings = $this->collectWith($this->createConnectionMock(static fn (string $sql): int => 0));

        static::assertCount(3, $findings);
        foreach ($findings as $finding) {
            static::assertSame(SecurityFinding::SEVERITY_OK, $finding->severity, $finding->id);
            static::assertSame(SecurityFinding::CATEGORY_RUNTIME, $finding->category, $finding->id);
            static::assertSame('none pending cleanup', $finding->current, $finding->id);
        }
    }

    public function testReportsMediumForStaleRecoveryTokens(): void
    {
        $connection = $this->createConnectionMock(static function (string $sql, array $params): int {
            if (str_contains($sql, 'customer_recovery')) {
                static::assertSame(['threshold' => self::formatExpected('2023-01-08 12:00:00')], $params);

                return 2;
            }

            return 0;
        });

        $findings = $this->collectWith($connection);

        $finding = $findings['stale-customer-recovery-tokens'];
        static::assertSame(SecurityFinding::SEVERITY_MEDIUM, $finding->severity);
        static::assertSame('Password recovery tokens', $finding->title);
        static::assertSame('2 recovery token(s) older than 48 hours', $finding->current);
        static::assertStringContainsString('customer.cleanup_customer_recovery', $finding->recommended);
    }

    public function testReportsMediumForExpiredPaymentTokens(): void
    {
        $connection = $this->createConnectionMock(static function (string $sql, array $params): int {
            if (str_contains($sql, 'payment_token')) {
                static::assertSame(['now' => self::formatExpected('2023-01-10 12:00:00')], $params);

                return 3;
            }

            return 0;
        });

        $findings = $this->collectWith($connection);

        $finding = $findings['stale-payment-tokens'];
        static::assertSame(SecurityFinding::SEVERITY_MEDIUM, $finding->severity);
        static::assertSame('3 expired payment token(s) still stored', $finding->current);
        static::assertStringContainsString('payment_token.cleanup', $finding->recommended);
    }

    public function testReportsMediumForStaleSalesChannelContexts(): void
    {
        $connection = $this->createConnectionMock(static function (string $sql, array $params): int {
            if (str_contains($sql, 'sales_channel_api_context')) {
                static::assertSame(['threshold' => self::formatExpected('2022-12-11 12:00:00')], $params);

                return 7;
            }

            return 0;
        });

        $findings = $this->collectWith($connection);

        $finding = $findings['stale-sales-channel-contexts'];
        static::assertSame(SecurityFinding::SEVERITY_MEDIUM, $finding->severity);
        static::assertSame('7 Store-API session(s) older than 30 days', $finding->current);
        static::assertStringContainsString('sales_channel_context.cleanup', $finding->recommended);
    }

    public function testReportsUnknownWhenQueryFails(): void
    {
        $connection = $this->createConnectionMock(static function (string $sql): int {
            if (str_contains($sql, 'payment_token')) {
                throw new \RuntimeException('connection lost');
            }

            return 0;
        });

        $findings = $this->collectWith($connection);

        static::assertSame(SecurityFinding::SEVERITY_UNKNOWN, $findings['stale-payment-tokens']->severity);
        static::assertSame(SecurityFinding::SEVERITY_OK, $findings['stale-customer-recovery-tokens']->severity);
        static::assertSame(SecurityFinding::SEVERITY_OK, $findings['stale-sales-channel-contexts']->severity);
    }

    /**
     * @param callable(string, array<string, mixed>): int $countCallback
     */
    private function createConnectionMock(callable $countCallback): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::exactly(3))
            ->method('fetchOne')
            ->willReturnCallback($countCallback);

        return $connection;
    }

    /**
     * @return array<string, SecurityFinding>
     */
    private function collectWith(Connection $connection): array
    {
        $collection = new SecurityCollection();
        (new StaleTokenCleanupChecker($connection, new MockClock(self::NOW), self::EXPIRE_DAYS))->collect($collection);

        $findings = [];
        foreach ($collection as $finding) {
            static::assertInstanceOf(SecurityFinding::class, $finding);
            $findings[$finding->id] = $finding;
        }

        return $findings;
    }

    private static function formatExpected(string $date): string
    {
        return (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
