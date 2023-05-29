<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class TaskChecker implements CheckerInterface
{
    public function __construct(private readonly Connection $connection, private readonly ParameterBagInterface $parameterBag)
    {
    }

    public function collect(HealthCollection $collection): void
    {
        $data = $this->connection->createQueryBuilder()
            ->select('s.scheduled_task_class', 's.next_execution_time')
            ->from('scheduled_task', 's')
            ->where('s.status NOT IN(:status)')
            ->setParameter('status', ['inactive', 'skipped'], Connection::PARAM_STR_ARRAY)
            ->fetchAllAssociative();

        $tasks = array_filter($data, function (array $task) {
            $taskClass = $task['scheduled_task_class'];

            // Old Shopware version
            if (!method_exists($taskClass, 'shouldRun')) {
                return true;
            }

            return $taskClass::shouldRun($this->parameterBag);
        });

        $now = new \DateTime();

        $tasks = array_filter($tasks, function (array $task) use($now) {
            $taskDate = new \DateTime($task['next_execution_time']);

            return $taskDate->diff($now)->i > 10;
        });

        if ($tasks === []) {
            $collection->add(SettingsResult::ok('scheduled_task', 'Scheduled tasks working scheduled'));

            return;
        }

        $collection->add(SettingsResult::warning('scheduled_task', 'The scheduled tasks are waiting for executing for more than 10 minutes'));
    }
}
