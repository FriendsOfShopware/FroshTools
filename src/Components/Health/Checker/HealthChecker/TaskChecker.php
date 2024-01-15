<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class TaskChecker implements HealthCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ParameterBagInterface $parameterBag
    ) {}

    public function collect(HealthCollection $collection): void
    {
        /** @var array{scheduled_task_class: class-string, next_execution_time: string}[] $data */
        $data = $this->connection->createQueryBuilder()
            ->select('s.scheduled_task_class', 's.next_execution_time')
            ->from('scheduled_task', 's')
            ->where('s.status NOT IN(:status)')
            ->setParameter('status', ['inactive', 'skipped'], ArrayParameterType::STRING)
            ->fetchAllAssociative();

        $tasks = array_filter($data, function (array $task) {
            $taskClass = $task['scheduled_task_class'];

            // Old Shopware version
            if (!method_exists($taskClass, 'shouldRun')) {
                return true;
            }

            return $taskClass::shouldRun($this->parameterBag);
        });

        $maxDiff = 10;
        $taskDateLimit = (new \DateTimeImmutable())->modify(\sprintf('-%d minutes', $maxDiff));
        $recommended = \sprintf('max %d mins', $maxDiff);

        $tasks = array_filter($tasks, fn(array $task) => new \DateTimeImmutable($task['next_execution_time']) < $taskDateLimit);

        if ($tasks === []) {
            $collection->add(SettingsResult::ok('scheduled_task', 'Scheduled tasks overdue', '0 mins', $recommended));

            return;
        }

        $maxTaskNextExecTime = 0;

        foreach ($tasks as $task) {
            $maxTaskNextExecTime = max((new \DateTimeImmutable($task['next_execution_time']))->getTimestamp(), $maxTaskNextExecTime);
        }

        $diff = round(abs(
            ($maxTaskNextExecTime - $taskDateLimit->getTimestamp()) / 60
        ));

        $collection->add(SettingsResult::warning('scheduled_task', 'Scheduled tasks overdue', \sprintf('%d mins', $diff), $recommended));
    }
}
