<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Acl\FroshToolsPrivileges;
use Frosh\Tools\Components\Scheduler\SymfonyScheduleCollector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Messenger\ScheduledStamp;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => [FroshToolsPrivileges::SCHEDULED_TASK_READ]])]
class SymfonySchedulerController extends AbstractController
{
    public function __construct(
        private readonly SymfonyScheduleCollector $collector,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[Route(path: '/symfony-scheduler', name: 'api.frosh.tools.symfony.scheduler.list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse($this->collector->collect());
    }

    #[Route(path: '/symfony-scheduler/{schedule}/{id}/run', name: 'api.frosh.tools.symfony.scheduler.run', defaults: ['_acl' => [FroshToolsPrivileges::SCHEDULED_TASK_UPDATE]], methods: ['POST'])]
    public function runTask(string $schedule, string $id): Response
    {
        $recurringMessage = $this->collector->findRecurringMessage($schedule, $id);

        if ($recurringMessage === null) {
            return new JsonResponse(['error' => 'Scheduled message not found'], Response::HTTP_NOT_FOUND);
        }

        $context = new MessageContext($schedule, $recurringMessage->getId(), $recurringMessage->getTrigger(), new \DateTimeImmutable());

        foreach ($recurringMessage->getMessages($context) as $message) {
            // Tasks declaring transports are already wrapped in a RedispatchMessage by the
            // scheduler compiler pass. Everything else has no routing of its own and would be
            // handled synchronously inside this request, so it is redispatched to async.
            if (!$message instanceof RedispatchMessage) {
                $message = new RedispatchMessage($message, 'async');
            }

            $this->messageBus->dispatch($message, [new ScheduledStamp($context)]);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
