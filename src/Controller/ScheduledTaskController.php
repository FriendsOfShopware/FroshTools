<?php declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\Handler\AbstractMessageHandler;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/api/_action/frosh-tools", defaults={"_routeScope"={"api"}, "_acl"={"frosh_tools:read"}})
 */
class ScheduledTaskController
{
    /**
     * @var AbstractMessageHandler[]
     */
    private $taskHandler;

    private EntityRepositoryInterface $scheduledTaskRepository;

    private TaskRegistry $taskRegistry;

    public function __construct(
        iterable $taskHandler,
        EntityRepositoryInterface $scheduledTaskRepository,
        TaskRegistry $taskRegistry
    ) {
        $this->taskHandler = $taskHandler;
        $this->scheduledTaskRepository = $scheduledTaskRepository;
        $this->taskRegistry = $taskRegistry;
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
                'status' => ScheduledTaskDefinition::STATUS_QUEUED,
                'nextExecutionTime' => new \DateTime(),
            ],
        ], $context);

        $className = $scheduledTask->getScheduledTaskClass();
        $task = new $className();
        $task->setTaskId($id);

        foreach ($this->taskHandler as $handler) {
            if (!$handler instanceof AbstractMessageHandler) {
                continue;
            }

            $handledMessages = $handler::getHandledMessages();
            if (!is_array($handledMessages)) {
                $handledMessages = iterator_to_array($handledMessages);
            }
            if (!in_array($className, $handledMessages, true)) {
                continue;
            }

            $handler->handle($task);
        }

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @Route(path="/scheduled-tasks/register", methods={"POST"}, name="api.frosh.tools.scheduled.tasks.register")
     */
    public function registerTasks(): JsonResponse
    {
        $this->taskRegistry->registerTasks();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function fetchTask(string $id, Context $context): ScheduledTaskEntity
    {
        $criteria = new Criteria([$id]);

        return $this->scheduledTaskRepository->search($criteria, $context)->first();
    }
}
