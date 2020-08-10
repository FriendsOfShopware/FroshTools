<?php declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 * @Route(path="/api/{version}/_action/frosh-tools")
 */
class ScheduledTaskController
{
    /**
     * @var AbstractMessageHandler[]
     */
    private $taskHandler;

    /**
     * @var EntityRepositoryInterface
     */
    private $scheduledTaskRepository;

    public function __construct(iterable $taskHandler, EntityRepositoryInterface $scheduledTaskRepository)
    {
        $this->taskHandler = $taskHandler;
        $this->scheduledTaskRepository = $scheduledTaskRepository;
    }

    /**
     * @Route(path="/scheduled-task/{id}", methods={"POST"}, name="api.frosh.tools.scheduled.task.run")
     */
    public function runTask(string $id, Context $context): JsonResponse
    {
        $scheduledTask = $this->fetchTask($id, $context);

        // Set status to allow running it
        $this->scheduledTaskRepository->update([
            [
                'id' => $id,
                'status' => ScheduledTaskDefinition::STATUS_QUEUED
            ]
        ], $context);

        $className = $scheduledTask->getScheduledTaskClass();
        $task = new $className();
        $task->setTaskId($id);

        foreach ($this->taskHandler as $handler) {
            if (!in_array($className, $handler::getHandledMessages(), true)) {
                continue;
            }

            $handler->handle($task);
        }

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function fetchTask(string $id, Context $context): ScheduledTaskEntity
    {
        $criteria = new Criteria([$id]);

        return $this->scheduledTaskRepository->search($criteria, $context)->first();
    }
}
