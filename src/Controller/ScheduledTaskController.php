<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskRunner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class ScheduledTaskController extends AbstractController
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        private readonly EntityRepository $scheduledTaskRepository,
        private readonly TaskRegistry $taskRegistry,
        private readonly TaskRunner $taskRunner,
    ) {}

    #[Route(path: '/scheduled-task/{id}', name: 'api.frosh.tools.scheduled.task.run', methods: ['POST'])]
    public function runTask(string $id, Context $context): JsonResponse
    {
        $scheduledTask = $this->fetchTask($id, $context);

        if (!$scheduledTask instanceof ScheduledTaskEntity) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        // Set status to allow running it
        $this->scheduledTaskRepository->update([
            [
                'id' => $id,
                'status' => ScheduledTaskDefinition::STATUS_QUEUED,
                'nextExecutionTime' => new \DateTime(),
            ],
        ], $context);

        $this->taskRunner->runSingleTask($scheduledTask->getName(), $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/scheduled-tasks/register', name: 'api.frosh.tools.scheduled.tasks.register', methods: ['POST'])]
    public function registerTasks(): JsonResponse
    {
        $this->taskRegistry->registerTasks();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function fetchTask(string $id, Context $context): ?ScheduledTaskEntity
    {
        $criteria = new Criteria([$id]);

        return $this->scheduledTaskRepository->search($criteria, $context)->getEntities()->first();
    }
}
