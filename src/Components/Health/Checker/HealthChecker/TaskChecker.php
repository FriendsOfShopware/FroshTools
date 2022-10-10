<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class TaskChecker implements CheckerInterface
{
    private EntityRepositoryInterface $scheduledTaskRepository;

    private ParameterBagInterface $parameterBag;

    public function __construct(EntityRepositoryInterface $scheduledTaskRepository, ParameterBagInterface $parameterBag)
    {
        $this->scheduledTaskRepository = $scheduledTaskRepository;
        $this->parameterBag = $parameterBag;
    }

    public function collect(HealthCollection $collection): void
    {
        $minutes = 10;

        $date = new \DateTime();
        $date->modify(sprintf('-%d minutes', $minutes));

        $criteria = new Criteria();
        $criteria->addFilter(
            new RangeFilter(
                'nextExecutionTime',
                ['lte' => $date->format(\DATE_ATOM)]
            )
        );
        $criteria->addFilter(new NotFilter(
            NotFilter::CONNECTION_AND,
            [
                new EqualsFilter('status', ScheduledTaskDefinition::STATUS_INACTIVE),
            ]
        ));

        $oldTasks = $this->scheduledTaskRepository
            ->search($criteria, Context::createDefaultContext())
        ;

        $oldTasks = $oldTasks->filter(function (ScheduledTaskEntity $task) {
            $taskClass = $task->getScheduledTaskClass();

            // Old Shopware version
            if (!method_exists($taskClass, 'shouldRun')) {
                return true;
            }

            return $taskClass::shouldRun($this->parameterBag);
        });

        if ($oldTasks->count() === 0) {
            $collection->add(SettingsResult::ok('frosh-tools.checker.scheduledTaskGood'));

            return;
        }

        $collection->add(SettingsResult::warning('frosh-tools.checker.scheduledTaskWarning'));
    }
}
