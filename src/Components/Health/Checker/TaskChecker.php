<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker;

use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\HealthResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;

class TaskChecker implements CheckerInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $scheduledTaskRepository;

    public function __construct(EntityRepositoryInterface $scheduledTaskRepository)
    {
        $this->scheduledTaskRepository = $scheduledTaskRepository;
    }

    public function collect(HealthCollection $collection): void
    {
        $date = new \DateTime();
        $date->modify(sprintf('-%d minutes', 10));

        $criteria = new Criteria();
        $criteria->addFilter(
            new RangeFilter(
                'nextExecutionTime',
                ['lte' => $date->format(DATE_ATOM)]
            )
        );

        $oldTasks = $this->scheduledTaskRepository
            ->search($criteria, Context::createDefaultContext())->count();

        if ($oldTasks === 0) {
            return;
        }

        $collection->add(HealthResult::warning('The scheduled tasks are waiting for executing for more than 10 minutes'));
    }
}
