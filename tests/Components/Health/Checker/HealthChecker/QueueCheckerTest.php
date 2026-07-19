<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\HealthChecker\QueueChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(QueueChecker::class)]
class QueueCheckerTest extends IntegrationTestCase
{
    private QueueChecker $checker;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->checker = static::getContainer()->get(QueueChecker::class);
        $this->connection = static::getContainer()->get(Connection::class);

        $this->connection->executeStatement('DELETE FROM messenger_messages');
    }

    public function testEmptyQueueResultsInInfoState(): void
    {
        $result = $this->collectQueueResult();

        static::assertSame(SettingsResult::INFO, $result->state);
    }

    public function testOldMessageResultsInWarningState(): void
    {
        $this->insertMessage('UTC_TIMESTAMP() - INTERVAL 2 HOUR');

        $result = $this->collectQueueResult();

        static::assertSame(SettingsResult::WARNING, $result->state);
    }

    public function testRecentMessageWithinGracePeriodResultsInOkState(): void
    {
        $this->insertMessage('UTC_TIMESTAMP() - INTERVAL 1 MINUTE');

        $result = $this->collectQueueResult();

        static::assertSame(SettingsResult::GREEN, $result->state);
    }

    private function insertMessage(string $availableAt): void
    {
        $this->connection->executeStatement(\sprintf(
            "INSERT INTO messenger_messages (body, headers, queue_name, created_at, available_at) VALUES ('a:0:{}', '[]', 'default', UTC_TIMESTAMP(), %s)",
            $availableAt,
        ));
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
}
