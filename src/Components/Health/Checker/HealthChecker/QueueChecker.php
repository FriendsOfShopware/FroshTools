<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class QueueChecker implements HealthCheckerInterface, CheckerInterface
{
    public function __construct(private readonly Connection $connection) {}

    public function collect(HealthCollection $collection): void
    {
        $maxDiff = 15;
        $oldMessageLimit = (new \DateTimeImmutable())->modify(\sprintf('-%d minutes', $maxDiff));

        $snippet = 'Open Queues';
        $recommended = \sprintf('max %d mins', $maxDiff);

        /** @var string|false $oldestMessageAt */
        $oldestMessageAt = $this->connection->fetchOne('SELECT available_at FROM messenger_messages WHERE available_at < UTC_TIMESTAMP() ORDER BY available_at ASC LIMIT 1');

        if (\is_string($oldestMessageAt)) {
            $diff = round(abs(
                ((new \DateTime($oldestMessageAt . ' UTC'))->getTimestamp() - $oldMessageLimit->getTimestamp()) / 60
            ));

            if ($diff > $maxDiff) {
                $result = SettingsResult::warning('queue', $snippet, $diff . ' mins', $recommended);
            } else {
                $result = SettingsResult::ok('queue', $snippet, $diff . ' mins', $recommended);
            }
        } else {
            $result = SettingsResult::info('queue', $snippet, 'unknown', $recommended);
        }

        $result->url = 'https://developer.shopware.com/docs/guides/hosting/infrastructure/message-queue';
        $collection->add($result);
    }
}
