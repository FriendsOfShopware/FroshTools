<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class QueueChecker implements CheckerInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function collect(HealthCollection $collection): void
    {
        $oldMessageLimit = (new \DateTimeImmutable())->modify('-15 minutes');

        /** @var string|false $oldestMessageAt */
        $oldestMessageAt = $this->connection->fetchOne('SELECT available_at FROM messenger_messages ORDER BY available_at ASC LIMIT 1');

        if (is_string($oldestMessageAt) && new \DateTimeImmutable($oldestMessageAt . ' UTC') < $oldMessageLimit) {
            $result = SettingsResult::warning('queue', 'Open Queues older than 15 minutes found');
        } else {
            $result = SettingsResult::ok('queue', 'Queues working good');
        }

        $result->url = 'https://developer.shopware.com/docs/guides/hosting/infrastructure/message-queue';
        $collection->add($result);
    }
}
