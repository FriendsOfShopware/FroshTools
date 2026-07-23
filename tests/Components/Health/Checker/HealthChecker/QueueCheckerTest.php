<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\HealthChecker\QueueChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Kernel + DB coverage for QueueChecker (QueryBuilder against real messenger_messages).
 * Fast mock-based edge cases live in QueueCheckerUnitTest.
 */
#[CoversClass(QueueChecker::class)]
class QueueCheckerTest extends IntegrationTestCase
{
    private QueueChecker $checker;

    private Connection $connection;

    private SystemConfigService $configService;

    protected function setUp(): void
    {
        $this->checker = static::getContainer()->get(QueueChecker::class);
        $this->connection = static::getContainer()->get(Connection::class);
        $this->configService = static::getContainer()->get(SystemConfigService::class);

        $this->connection->executeStatement('DELETE FROM messenger_messages');
        $this->resetMonitorConfig();
    }

    public function testEmptyQueueResultsInInfoState(): void
    {
        $result = $this->collectQueueResult();

        static::assertSame(SettingsResult::INFO, $result->state);
        static::assertSame('0 mins', $result->current);
    }

    public function testOldMessageResultsInWarningState(): void
    {
        $this->insertMessage('UTC_TIMESTAMP() - INTERVAL 2 HOUR', 'async');

        $result = $this->collectQueueResult();

        static::assertSame(SettingsResult::WARNING, $result->state);
        static::assertStringContainsString('async', $result->current);
    }

    public function testRecentMessageWithinGracePeriodResultsInOkState(): void
    {
        $this->insertMessage('UTC_TIMESTAMP() - INTERVAL 1 MINUTE', 'async');

        $result = $this->collectQueueResult();

        static::assertSame(SettingsResult::GREEN, $result->state);
    }

    public function testFailedQueueMessagesAreIgnoredByDefault(): void
    {
        $this->insertMessage('UTC_TIMESTAMP() - INTERVAL 2 HOUR', 'async_failed');

        $result = $this->collectQueueResult();

        static::assertSame(SettingsResult::INFO, $result->state);
    }

    public function testFailedQueueMessagesAreCountedWhenExclusionDisabled(): void
    {
        $this->configService->set('FroshTools.config.monitorExcludeFailedQueues', false);
        $this->insertMessage('UTC_TIMESTAMP() - INTERVAL 2 HOUR', 'async_failed');

        $result = $this->collectQueueResult();

        static::assertSame(SettingsResult::WARNING, $result->state);
        static::assertStringContainsString('async_failed', $result->current);
    }

    public function testAllowlistIgnoresQueuesOutsideTheList(): void
    {
        $this->configService->set('FroshTools.config.monitorQueues', 'async');
        $this->insertMessage('UTC_TIMESTAMP() - INTERVAL 2 HOUR', 'low_priority');

        $result = $this->collectQueueResult();

        static::assertSame(SettingsResult::INFO, $result->state);
    }

    public function testAllowlistMonitorsListedQueue(): void
    {
        $this->configService->set('FroshTools.config.monitorQueues', 'async, low_priority');
        $this->insertMessage('UTC_TIMESTAMP() - INTERVAL 2 HOUR', 'async');

        $result = $this->collectQueueResult();

        static::assertSame(SettingsResult::WARNING, $result->state);
        static::assertStringContainsString('async', $result->current);
    }

    public function testPerQueueGraceTimeIsApplied(): void
    {
        $this->configService->set('FroshTools.config.monitorQueueGraceTime', 15);
        $this->configService->set('FroshTools.config.monitorQueueGraceTimes', 'low_priority:120');
        $this->insertMessage('UTC_TIMESTAMP() - INTERVAL 30 MINUTE', 'low_priority');

        $result = $this->collectQueueResult();

        static::assertSame(SettingsResult::GREEN, $result->state);
        static::assertSame('max 120 mins', $result->recommended);
    }

    private function insertMessage(string $availableAt, string $queueName = 'default'): void
    {
        $this->connection->executeStatement(
            \sprintf(
                "INSERT INTO messenger_messages (body, headers, queue_name, created_at, available_at) VALUES ('a:0:{}', '[]', %s, UTC_TIMESTAMP(), %s)",
                $this->connection->quote($queueName),
                $availableAt,
            ),
        );
    }

    private function collectQueueResult(): SettingsResult
    {
        $collection = new HealthCollection();
        $this->checker->collect($collection);

        foreach ($collection->getElements() as $element) {
            if ($element->id === 'queue') {
                return $element;
            }
        }

        static::fail('HealthCollection does not contain a result with id "queue"');
    }

    private function resetMonitorConfig(): void
    {
        $this->configService->delete('FroshTools.config.monitorExcludeFailedQueues');
        $this->configService->delete('FroshTools.config.monitorQueues');
        $this->configService->delete('FroshTools.config.monitorQueueGraceTimes');
        $this->configService->set('FroshTools.config.monitorQueueGraceTime', 15);
    }
}
