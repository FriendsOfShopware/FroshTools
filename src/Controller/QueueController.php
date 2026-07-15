<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Queue\QueueRegistry;
use Frosh\Tools\Components\Queue\WorkerHeartbeatListener;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Routing\Attribute\Route;

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
        private readonly ServiceLocator $transportLocator,
        private readonly QueueRegistry $queueRegistry,
        #[Autowire(service: 'cache.object')]
        private readonly CacheItemPoolInterface $objectCache,
    ) {
    }

    #[Route(path: '/queue/transports', name: 'api.frosh.tools.queue.transports', methods: ['GET'])]
    public function transports(): JsonResponse
    {
        $heartbeats = $this->getWorkerHeartbeats();
        $transports = [];

        foreach ($this->queueRegistry->all() as $adapter) {
            $name = $adapter->getName();

            $transports[] = [
                'name' => $name,
                'type' => $adapter->getType(),
                'size' => $adapter->getMessageCount(),
                'oldestMessageAgeSeconds' => $adapter->getOldestMessageAge(),
                'workerLastSeenSeconds' => isset($heartbeats[$name]) ? max(0, time() - $heartbeats[$name]) : null,
                'browsable' => $adapter->isBrowsable(),
                'requeuesOnBrowse' => $adapter->requeuesOnBrowse(),
                'removable' => $adapter->supportsRemove(),
                'retryable' => $adapter->supportsRetry(),
                'purgeable' => $adapter->supportsPurge(),
            ];
        }

        return new JsonResponse($transports);
    }

    #[Route(path: '/queue/transport/{name}/messages/{id}/retry', name: 'api.frosh.tools.queue.message.retry', requirements: ['id' => '.+'], methods: ['POST'])]
    public function retryMessage(string $name, string $id): JsonResponse
    {
        $adapter = $this->queueRegistry->has($name) ? $this->queueRegistry->get($name) : null;
        if ($adapter === null) {
            return new JsonResponse(['error' => \sprintf('Unknown transport "%s"', $name)], Response::HTTP_NOT_FOUND);
        }

        if (!$adapter->supportsRetry()) {
            return new JsonResponse(['error' => \sprintf('The transport "%s" does not support retrying messages', $name)], Response::HTTP_BAD_REQUEST);
        }

        return $this->executeMessageAction(static fn () => $adapter->retryMessage($id));
    }

    #[Route(path: '/queue/transport/{name}/messages/{id}', name: 'api.frosh.tools.queue.message.delete', requirements: ['id' => '.+'], methods: ['DELETE'])]
    public function deleteMessage(string $name, string $id): JsonResponse
    {
        $adapter = $this->queueRegistry->has($name) ? $this->queueRegistry->get($name) : null;
        if ($adapter === null) {
            return new JsonResponse(['error' => \sprintf('Unknown transport "%s"', $name)], Response::HTTP_NOT_FOUND);
        }

        if (!$adapter->supportsRemove()) {
            return new JsonResponse(['error' => \sprintf('The transport "%s" does not support removing single messages', $name)], Response::HTTP_BAD_REQUEST);
        }

        return $this->executeMessageAction(static fn () => $adapter->removeMessage($id));
    }

    #[Route(path: '/queue/transport/{name}', name: 'api.frosh.tools.queue.transport.purge', methods: ['DELETE'])]
    public function purgeTransport(string $name): JsonResponse
    {
        $adapter = $this->queueRegistry->has($name) ? $this->queueRegistry->get($name) : null;
        if ($adapter === null) {
            return new JsonResponse(['error' => \sprintf('Unknown transport "%s"', $name)], Response::HTTP_NOT_FOUND);
        }

        if (!$adapter->supportsPurge()) {
            return new JsonResponse(['error' => \sprintf('The transport "%s" does not support purging', $name)], Response::HTTP_BAD_REQUEST);
        }

        return $this->executeMessageAction(static fn () => $adapter->purge());
    }

    #[Route(path: '/queue/transport/{name}/messages', name: 'api.frosh.tools.queue.messages', methods: ['GET'])]
    public function messages(string $name, Request $request): JsonResponse
    {
        if (!$this->queueRegistry->has($name)) {
            return new JsonResponse(['error' => \sprintf('Unknown transport "%s"', $name)], Response::HTTP_NOT_FOUND);
        }

        $adapter = $this->queueRegistry->get($name);

        if (!$adapter->isBrowsable()) {
            return new JsonResponse(['error' => \sprintf('The transport "%s" does not support browsing messages', $name)], Response::HTTP_BAD_REQUEST);
        }

        $limit = max(1, min(100, $request->query->getInt('limit', 10)));

        try {
            $messages = $adapter->getMessages($limit);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'transport' => $adapter->getName(),
            'type' => $adapter->getType(),
            'size' => $adapter->getMessageCount(),
            'messages' => $messages,
        ]);
    }

    #[Route(path: '/queue/list', name: 'api.frosh.tools.queue.list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $incrementer = $this->incrementer->get('message_queue');

        $list = $incrementer->list('message_queue_stats', -1);
        $queueData = array_map(static fn (array $entry) => [
            'name' => $entry['key'],
            'size' => (int) $entry['count'],
        ], array_values($list));

        $this->getMessengerStats($queueData);

        return new JsonResponse($queueData);
    }

    #[Route(path: '/queue', name: 'api.frosh.tools.queue.clear', methods: ['DELETE'])]
    public function resetQueue(): JsonResponse
    {
        $incrementer = $this->incrementer->get('message_queue');
        $incrementer->reset('message_queue_stats');

        $this->connection->executeStatement('TRUNCATE `messenger_messages`');
        $this->connection->executeStatement('UPDATE product_export SET is_running = 0');

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param callable(): void $action
     */
    private function executeMessageAction(callable $action): JsonResponse
    {
        try {
            $action();
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, int>
     */
    private function getWorkerHeartbeats(): array
    {
        try {
            $item = $this->objectCache->getItem(WorkerHeartbeatListener::CACHE_KEY);
            $heartbeats = $item->isHit() ? $item->get() : [];

            return \is_array($heartbeats) ? $heartbeats : [];
        } catch (\Throwable) {
            return [];
        }
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
                continue;
            }

            $queueData[] = [
                'name' => $transportName,
                'size' => $transport->getMessageCount(),
            ];
        }

        usort($queueData, static fn (array $a, array $b) => $b['size'] <=> $a['size']);
    }

    /**
     * @return array<string>
     */
    private function getTransportNames(): array
    {
        $transportNames = array_keys($this->transportLocator->getProvidedServices());

        return array_filter($transportNames, static fn (string $transportName) => str_starts_with($transportName, 'messenger.transport'));
    }
}
