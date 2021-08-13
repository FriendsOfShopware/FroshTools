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
        $oldestMessage = (int) $this->connection->fetchOne('SELECT IFNULL(MIN(published_at), 0) FROM enqueue');

        if ($oldestMessage === 0) {
            $collection->add(HealthResult::ok('frosh-tools.checker.queuesGood'));

            return;
        }

        $oldestMessage /= 10000;
        $minutes = 15;

        // When the oldest message is older then $minutes minutes
        if (($oldestMessage + ($minutes * 60)) < time()) {
            $collection->add(HealthResult::warning('frosh-tools.checker.queuesWarning', ['minutes' => $minutes]));
        } else {
            $collection->add(HealthResult::ok('frosh-tools.checker.queuesGood'));
        }
    }
}
