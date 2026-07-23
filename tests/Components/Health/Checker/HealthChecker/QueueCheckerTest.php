<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Frosh\Tools\Components\Health\Checker\HealthChecker\QueueChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
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
        $query = $this->createQueryBuilderMock(false);
        $query->expects(static::once())
            ->method('andWhere')
            ->with('queue_name NOT LIKE :failedPattern')
            ->willReturnSelf();
        $query->expects(static::once())
            ->method('setParameter')
            ->with('failedPattern', '%failed%')
            ->willReturnSelf();

        $result = $this->collectWith(
            connection: $this->connectionReturning($query),
            config: [],
        );

        static::assertSame(SettingsResult::INFO, $result->state);
    }

    public function testFailedQueuesCanBeIncluded(): void
    {
        $query = $this->createQueryBuilderMock([
            'available_at' => (new \DateTimeImmutable('-2 hours', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            'queue_name' => 'async_failed',
        ]);
        $query->expects(static::never())->method('andWhere');

        $result = $this->collectWith(
            connection: $this->connectionReturning($query),
            config: ['FroshTools.config.monitorExcludeFailedQueues' => false],
        );

        static::assertSame(SettingsResult::WARNING, $result->state);
        static::assertStringContainsString('async_failed', $result->current);
    }

    public function testAllowlistRestrictsMonitoredQueues(): void
    {
        $query = $this->createQueryBuilderMock([
            'available_at' => (new \DateTimeImmutable('-5 minutes', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            'queue_name' => 'async',
        ]);

        $andWhere = [];
        $query->expects(static::exactly(2))
            ->method('andWhere')
            ->willReturnCallback(static function (string $predicate) use ($query, &$andWhere): QueryBuilder {
                $andWhere[] = $predicate;

                return $query;
            });

        $parameters = [];
        $query->expects(static::exactly(2))
            ->method('setParameter')
            ->willReturnCallback(static function (string $name, mixed $value, mixed $type = null) use ($query, &$parameters): QueryBuilder {
                $parameters[$name] = ['value' => $value, 'type' => $type];

                return $query;
            });

        $result = $this->collectWith(
            connection: $this->connectionReturning($query),
            config: [
                'FroshTools.config.monitorQueues' => 'async, low_priority',
                'FroshTools.config.monitorQueueGraceTime' => 15,
            ],
        );

        static::assertContains('queue_name NOT LIKE :failedPattern', $andWhere);
        static::assertContains('queue_name IN (:queues)', $andWhere);
        static::assertSame('%failed%', $parameters['failedPattern']['value']);
        static::assertSame(['async', 'low_priority'], $parameters['queues']['value']);
        static::assertSame(ArrayParameterType::STRING, $parameters['queues']['type']);
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
        return $this->collectWith(
            connection: $this->connectionReturning($this->createQueryBuilderMock($connectionRows)),
            config: $config,
        );
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

    /**
     * @param array{available_at: string, queue_name: string}|false $row
     */
    private function createQueryBuilderMock(array|false $row): QueryBuilder&MockObject
    {
        $query = $this->createMock(QueryBuilder::class);
        $query->method('select')->willReturnSelf();
        $query->method('from')->willReturnSelf();
        $query->method('where')->willReturnSelf();
        $query->method('andWhere')->willReturnSelf();
        $query->method('orderBy')->willReturnSelf();
        $query->method('setMaxResults')->willReturnSelf();
        $query->method('setParameter')->willReturnSelf();
        $query->method('fetchAssociative')->willReturn($row);

        return $query;
    }

    private function connectionReturning(QueryBuilder $query): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($query);

        return $connection;
    }
}
