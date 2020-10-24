<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\HealthResult;

class QueueChecker implements CheckerInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function collect(HealthCollection $collection): void
    {
        $oldestMessage = (int) $this->connection->fetchColumn('SELECT IFNULL(MIN(published_at), 0) FROM enqueue');

        if ($oldestMessage === 0) {
            $collection->add(HealthResult::ok('Queues working good'));
            return;
        }

        $oldestMessage /= 10000;

        // When the oldest message is older then 15 minutes
        if (($oldestMessage + (15 * 60)) < time()) {
            $collection->add(HealthResult::warning('Queues are older than 15 minutes'));
        } else {
            $collection->add(HealthResult::ok('Queues working good'));
        }
    }
}
