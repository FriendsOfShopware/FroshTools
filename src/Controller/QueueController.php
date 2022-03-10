<?php declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 * @Route(path="/api/_action/frosh-tools")
 */
class QueueController
{
    private Connection $connection;
    private IncrementGatewayRegistry $incrementer;

    public function __construct(Connection $connection, IncrementGatewayRegistry $incrementer)
    {
        $this->connection = $connection;
        $this->incrementer = $incrementer;
    }

    /**
     * @Route(path="/queue/list", methods={"GET"}, name="api.frosh.tools.queue.list")
     */
    public function list(): JsonResponse
    {
        $incrementer = $this->incrementer->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);

        $list = $incrementer->list('message_queue_stats', -1);
        return new JsonResponse(array_map(static function (array $entry) {
            return [
                'name' => $entry['key'],
                'size' => (int) $entry['count'],
            ];
        }, array_values($list)));
    }

    /**
     * @Route(path="/queue", methods={"DELETE"}, name="api.frosh.tools.queue.clear")
     */
    public function resetQueue(): JsonResponse
    {
        $incrementer = $this->incrementer->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);
        $incrementer->reset('message_queue_stats');

        $this->connection->executeStatement('TRUNCATE `enqueue`');
        $this->connection->executeStatement('TRUNCATE `dead_message`');

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
