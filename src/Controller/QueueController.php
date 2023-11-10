<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class QueueController extends AbstractController
{
    /**
     * @param ServiceLocator<ReceiverInterface> $transportLocator
     */
    public function __construct(
        private readonly Connection $connection,
        #[Autowire(service: 'shopware.increment.gateway.registry')]
        private readonly IncrementGatewayRegistry $incrementer,
        #[Autowire(service: 'messenger.receiver_locator')]
        private readonly ServiceLocator $transportLocator
    ) {}

    #[Route(path: '/queue/list', name: 'api.frosh.tools.queue.list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $incrementer = $this->incrementer->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);

        $list = $incrementer->list('message_queue_stats', -1);
        $queueData = array_map(static fn(array $entry) => [
            'name' => $entry['key'],
            'size' => (int) $entry['count'],
        ], array_values($list));

        $this->getMessengerStats($queueData);

        return new JsonResponse($queueData);
    }

    #[Route(path: '/queue', name: 'api.frosh.tools.queue.clear', methods: ['DELETE'])]
    public function resetQueue(): JsonResponse
    {
        $incrementer = $this->incrementer->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);
        $incrementer->reset('message_queue_stats');

        $this->connection->executeStatement('TRUNCATE `messenger_messages`');
        $this->connection->executeStatement('UPDATE product_export SET is_running = 0');

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<mixed> $queueData
     */
    private function getMessengerStats(array &$queueData): void
    {
        foreach ($this->getTransportNames() as $transportName) {
            if (!$this->transportLocator->has($transportName)) {
                continue;
            }

            $transport = $this->transportLocator->get($transportName);
            if (!$transport instanceof MessageCountAwareInterface) {
                $queueData[] = [
                    'name' => $transportName,
                    'size' => 'unknown',
                ];

                continue;
            }

            $queueData[] = [
                'name' => $transportName,
                'size' => $transport->getMessageCount(),
            ];
        }

        usort($queueData, static fn(array $a, array $b) => $b['size'] <=> $a['size']);
    }

    /**
     * @return array<string>
     */
    private function getTransportNames(): array
    {
        $transportNames = array_keys($this->transportLocator->getProvidedServices());

        return array_filter($transportNames, static fn(string $transportName) => str_starts_with($transportName, 'messenger.transport'));
    }
}
