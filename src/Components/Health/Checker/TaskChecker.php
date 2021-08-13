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

        $oldTasks = $this->scheduledTaskRepository
            ->searchIds($criteria, Context::createDefaultContext())->getIds();

        if (count($oldTasks) === 0) {
            $collection->add(HealthResult::ok('frosh-tools.checker.scheduledTaskGood'));

            return;
        }

        $collection->add(HealthResult::warning('frosh-tools.checker.scheduledTaskWarning', ['minutes' => $minutes]));
    }
}
