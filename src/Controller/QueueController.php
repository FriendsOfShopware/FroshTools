<?php declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class QueueController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly IncrementGatewayRegistry $incrementer
    ) {
    }

    #[Route(path: '/queue/list', name: 'api.frosh.tools.queue.list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $incrementer = $this->incrementer->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);

        $list = $incrementer->list('message_queue_stats', -1);

        return new JsonResponse(array_map(static fn (array $entry) => [
            'name' => $entry['key'],
            'size' => (int) $entry['count'],
        ], array_values($list)));
    }

    #[Route(path: '/queue', name: 'api.frosh.tools.queue.clear', methods: ['DELETE'])]
    public function resetQueue(): JsonResponse
    {
        $incrementer = $this->incrementer->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);
        $incrementer->reset('message_queue_stats');

        $this->connection->executeStatement('TRUNCATE `messenger_messages`');

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
