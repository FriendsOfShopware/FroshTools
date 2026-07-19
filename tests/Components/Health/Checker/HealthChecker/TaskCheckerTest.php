<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\HealthChecker\TaskChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TaskChecker::class)]
class TaskCheckerTest extends IntegrationTestCase
{
    private TaskChecker $checker;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->checker = static::getContainer()->get(TaskChecker::class);
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testCollectReportsOkWhenNoTaskIsOverdue(): void
    {
        $this->connection->executeStatement('UPDATE scheduled_task SET next_execution_time = DATE_ADD(NOW(), INTERVAL 1 DAY)');

        $result = $this->collectScheduledTaskResult();

        static::assertSame(SettingsResult::GREEN, $result->state);
    }

    public function testCollectReportsWarningWhenTaskIsOverdue(): void
    {
        $this->connection->executeStatement('UPDATE scheduled_task SET next_execution_time = DATE_ADD(NOW(), INTERVAL 1 DAY)');

        // The checker ignores inactive and skipped tasks, so only qualifying rows are made overdue
        $this->connection->executeStatement(
            "UPDATE scheduled_task SET next_execution_time = DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE status NOT IN ('inactive', 'skipped')"
        );

        $result = $this->collectScheduledTaskResult();

        static::assertSame(SettingsResult::WARNING, $result->state);
    }

    private function collectScheduledTaskResult(): SettingsResult
    {
        $collection = new HealthCollection();
        $this->checker->collect($collection);

        foreach ($collection->getElements() as $element) {
            if ($element->id === 'scheduled_task') {
                return $element;
            }
        }

        static::fail('TaskChecker did not add a result with id "scheduled_task" to the collection');
    }
}
