<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Controller\ScheduledTaskController;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ScheduledTaskController::class)]
class ScheduledTaskControllerTest extends IntegrationTestCase
{
    private ScheduledTaskController $controller;

    protected function setUp(): void
    {
        $this->controller = static::getContainer()->get(ScheduledTaskController::class);
    }

    public function testDeactivateTaskSetsStatusInactive(): void
    {
        $task = $this->fetchAnyTask();
        $context = Context::createDefaultContext();

        $response = $this->controller->deactivateTask($task->getId(), $context);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $reloaded = $this->reloadTask($task->getId(), $context);
        static::assertSame(ScheduledTaskDefinition::STATUS_INACTIVE, $reloaded->getStatus());
    }

    public function testScheduleTaskImmediatelyMovesNextExecutionTimeToNow(): void
    {
        $task = $this->fetchAnyTask();
        $context = Context::createDefaultContext();

        // In the HTTP flow the JsonRequestTransformerListener decodes the JSON body
        // into the request parameter bag, so parameters are passed directly here.
        $request = new Request([], ['immediately' => true]);
        $response = $this->controller->scheduleTask($request, $task->getId(), $context);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $reloaded = $this->reloadTask($task->getId(), $context);
        static::assertSame(ScheduledTaskDefinition::STATUS_SCHEDULED, $reloaded->getStatus());

        $nextExecutionTime = $reloaded->getNextExecutionTime();
        static::assertNotNull($nextExecutionTime);
        static::assertLessThanOrEqual(time(), $nextExecutionTime->getTimestamp());
    }

    public function testScheduleTaskWithUnknownIdReturns404(): void
    {
        $response = $this->controller->scheduleTask(new Request(), Uuid::randomHex(), Context::createDefaultContext());

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        static::assertArrayHasKey('error', $body);
    }

    private function fetchAnyTask(): ScheduledTaskEntity
    {
        $task = static::getContainer()->get('scheduled_task.repository')
            ->search((new Criteria())->setLimit(1), Context::createDefaultContext())
            ->getEntities()
            ->first();

        if (!$task instanceof ScheduledTaskEntity) {
            static::markTestSkipped('No scheduled tasks available in the database');
        }

        return $task;
    }

    private function reloadTask(string $id, Context $context): ScheduledTaskEntity
    {
        $task = static::getContainer()->get('scheduled_task.repository')
            ->search(new Criteria([$id]), $context)
            ->getEntities()
            ->first();

        static::assertInstanceOf(ScheduledTaskEntity::class, $task);

        return $task;
    }
}
