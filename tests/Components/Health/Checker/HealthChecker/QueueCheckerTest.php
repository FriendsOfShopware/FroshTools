<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\HealthChecker\QueueChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Components\Queue\QueueRegistry;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection as MessengerConnection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

/**
 * Kernel + DB coverage for QueueChecker through configured Doctrine transports.
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
        $this->connection = static::getContainer()->get(Connection::class);
        $this->configService = static::getContainer()->get(SystemConfigService::class);

        $receivers = [];
        foreach (['async', 'async_failed', 'low_priority'] as $name) {
            $transport = new DoctrineTransport(new MessengerConnection(['queue_name' => $name], $this->connection), new PhpSerializer());
            $receivers[$name] = static fn () => $transport;
        }
        $registry = new QueueRegistry(new ServiceLocator($receivers), $this->createMock(MessageBusInterface::class));
        $this->checker = new QueueChecker($registry, $this->configService);

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

    public function testShorterGraceQueueIsNotMaskedByOlderLooserQueue(): void
    {
        $this->configService->set('FroshTools.config.monitorQueueGraceTime', 15);
        $this->configService->set('FroshTools.config.monitorQueueGraceTimes', 'async:10, low_priority:120');
        // Older message on a loose queue must not hide a newer overdue tight queue.
        $this->insertMessage('UTC_TIMESTAMP() - INTERVAL 90 MINUTE', 'low_priority');
        $this->insertMessage('UTC_TIMESTAMP() - INTERVAL 20 MINUTE', 'async');

        $result = $this->collectQueueResult();

        static::assertSame(SettingsResult::WARNING, $result->state);
        static::assertStringContainsString('async', $result->current);
        static::assertSame('max 10 mins', $result->recommended);
    }

    public function testStaleMessagesFromUnconfiguredQueueAreIgnored(): void
    {
        $this->insertMessage('UTC_TIMESTAMP() - INTERVAL 90 DAY', 'default');

        static::assertSame('0 mins', $this->collectQueueResult()->current);
    }

    public function testFutureMessagesAreNotOverdue(): void
    {
        $this->insertMessage('UTC_TIMESTAMP() + INTERVAL 1 HOUR', 'async');

        static::assertSame(SettingsResult::INFO, $this->collectQueueResult()->state);
    }

    public function testClaimedMessagesDoNotCauseAnAgeWarning(): void
    {
        $this->insertMessage('UTC_TIMESTAMP() - INTERVAL 2 HOUR', 'async');
        $this->connection->executeStatement('UPDATE messenger_messages SET delivered_at = UTC_TIMESTAMP()');

        static::assertSame('0 mins', $this->collectQueueResult()->current);
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
            if ('queue' === $element->id) {
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
