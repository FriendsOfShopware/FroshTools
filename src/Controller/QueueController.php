<?php declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 * @Route(path="/api/_action/frosh-tools")
 */
class QueueController
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @Route(path="/queue", methods={"DELETE"}, name="api.frosh.tools.queue.clear")
     */
    public function resetQueue(): JsonResponse
    {
        $this->connection->executeUpdate('TRUNCATE `message_queue_stats`');
        $this->connection->executeUpdate('TRUNCATE `enqueue`');
        $this->connection->executeUpdate('TRUNCATE `dead_message`');

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
