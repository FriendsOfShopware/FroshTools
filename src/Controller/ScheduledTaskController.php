<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class ScheduledTaskController extends AbstractController
{
    /**
     * @param iterable<ScheduledTaskHandler> $taskHandler
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(# @phpstan-ignore-line
        #[TaggedIterator('messenger.message_handler')]
        private readonly iterable $taskHandler,
        private readonly EntityRepository $scheduledTaskRepository,
        private readonly TaskRegistry $taskRegistry
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

        $className = $scheduledTask->getScheduledTaskClass();
        /** @var ScheduledTask $task */
        $task = new $className();
        $task->setTaskId($id);

        foreach ($this->taskHandler as $handler) {
            if (!$handler instanceof ScheduledTaskHandler) {
                continue;
            }

            // @phpstan-ignore-next-line
            $handledMessages = $handler::getHandledMessages();
            if (!\is_array($handledMessages)) {
                $handledMessages = iterator_to_array($handledMessages);
            }
            if (!\in_array($className, $handledMessages, true)) {
                continue;
            }

            // calls the __invoke() method of the abstract ScheduledTaskHandler
            $handler($task);
        }

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
