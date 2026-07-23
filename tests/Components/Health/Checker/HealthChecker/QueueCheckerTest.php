<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\HealthChecker\QueueChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(QueueChecker::class)]
class QueueCheckerTest extends TestCase
{
    public function testEmptyQueueResultsInInfoState(): void
    {
        $result = $this->collect(
            connectionRows: false,
            config: [],
        );

        static::assertSame(SettingsResult::INFO, $result->state);
        static::assertSame('0 mins', $result->current);
    }

    public function testOldMessageResultsInWarningState(): void
    {
        $result = $this->collect(
            connectionRows: [
                'available_at' => (new \DateTimeImmutable('-2 hours', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
                'queue_name' => 'async',
            ],
            config: ['FroshTools.config.monitorQueueGraceTime' => 15],
        );

        static::assertSame(SettingsResult::WARNING, $result->state);
        static::assertStringContainsString('async', $result->current);
    }

    public function testRecentMessageWithinGracePeriodResultsInOkState(): void
    {
        $result = $this->collect(
            connectionRows: [
                'available_at' => (new \DateTimeImmutable('-1 minute', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
                'queue_name' => 'async',
            ],
            config: ['FroshTools.config.monitorQueueGraceTime' => 15],
        );

        static::assertSame(SettingsResult::GREEN, $result->state);
    }

    public function testMessageJustOverGraceIsWarningNotDoubleGrace(): void
    {
        // Regression: previous formula effectively required age > 2 * grace.
        $result = $this->collect(
            connectionRows: [
                'available_at' => (new \DateTimeImmutable('-20 minutes', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
                'queue_name' => 'async',
            ],
            config: ['FroshTools.config.monitorQueueGraceTime' => 15],
        );

        static::assertSame(SettingsResult::WARNING, $result->state);
    }

    public function testFailedQueuesAreExcludedByDefault(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('fetchAssociative')
            ->with(
                static::callback(static function (string $sql): bool {
                    return \str_contains($sql, 'NOT LIKE') && \str_contains($sql, 'queue_name');
                }),
                static::callback(static function (array $params): bool {
                    return $params === ['%failed%'];
                }),
            )
            ->willReturn(false);

        $result = $this->collectWith(connection: $connection, config: []);

        static::assertSame(SettingsResult::INFO, $result->state);
    }

    public function testFailedQueuesCanBeIncluded(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('fetchAssociative')
            ->with(
                static::callback(static function (string $sql): bool {
                    return !\str_contains($sql, 'NOT LIKE');
                }),
                static::equalTo([]),
            )
            ->willReturn([
                'available_at' => (new \DateTimeImmutable('-2 hours', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
                'queue_name' => 'async_failed',
            ]);

        $result = $this->collectWith(
            connection: $connection,
            config: ['FroshTools.config.monitorExcludeFailedQueues' => false],
        );

        static::assertSame(SettingsResult::WARNING, $result->state);
        static::assertStringContainsString('async_failed', $result->current);
    }

    public function testAllowlistRestrictsMonitoredQueues(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('fetchAssociative')
            ->with(
                static::callback(static function (string $sql): bool {
                    return \str_contains($sql, 'IN (');
                }),
                static::equalTo(['%failed%', 'async', 'low_priority']),
            )
            ->willReturn([
                'available_at' => (new \DateTimeImmutable('-5 minutes', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
                'queue_name' => 'async',
            ]);

        $result = $this->collectWith(
            connection: $connection,
            config: [
                'FroshTools.config.monitorQueues' => 'async, low_priority',
                'FroshTools.config.monitorQueueGraceTime' => 15,
            ],
        );

        static::assertSame(SettingsResult::GREEN, $result->state);
    }

    public function testPerQueueGraceTimeOverridesDefault(): void
    {
        $result = $this->collect(
            connectionRows: [
                'available_at' => (new \DateTimeImmutable('-30 minutes', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
                'queue_name' => 'low_priority',
            ],
            config: [
                'FroshTools.config.monitorQueueGraceTime' => 15,
                'FroshTools.config.monitorQueueGraceTimes' => 'low_priority:60, async:10',
            ],
        );

        // 30 mins old with 60 min grace for low_priority → OK
        static::assertSame(SettingsResult::GREEN, $result->state);
        static::assertSame('max 60 mins', $result->recommended);
    }

    public function testPerQueueGraceTimeCanTightenDefault(): void
    {
        $result = $this->collect(
            connectionRows: [
                'available_at' => (new \DateTimeImmutable('-12 minutes', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
                'queue_name' => 'async',
            ],
            config: [
                'FroshTools.config.monitorQueueGraceTime' => 15,
                'FroshTools.config.monitorQueueGraceTimes' => 'async:10',
            ],
        );

        static::assertSame(SettingsResult::WARNING, $result->state);
        static::assertSame('max 10 mins', $result->recommended);
    }

    /**
     * @param array{available_at: string, queue_name: string}|false $connectionRows
     * @param array<string, mixed> $config
     */
    private function collect(array|false $connectionRows, array $config): SettingsResult
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn($connectionRows);

        return $this->collectWith($connection, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function collectWith(Connection $connection, array $config): SettingsResult
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('getInt')->willReturnCallback(
            static fn (string $key): int => (int) ($config[$key] ?? 0),
        );
        $configService->method('getString')->willReturnCallback(
            static fn (string $key): string => (string) ($config[$key] ?? ''),
        );
        $configService->method('get')->willReturnCallback(
            static fn (string $key): mixed => $config[$key] ?? null,
        );

        $collection = new HealthCollection();
        (new QueueChecker($connection, $configService))->collect($collection);

        foreach ($collection->getElements() as $element) {
            if ($element->id === 'queue') {
                return $element;
            }
        }

        static::fail('HealthCollection does not contain a result with id "queue"');
    }
}
