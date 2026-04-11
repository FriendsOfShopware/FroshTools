<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

class QueueChecker implements HealthCheckerInterface, CheckerInterface
{
    private const URL = 'https://developer.shopware.com/docs/guides/hosting/infrastructure/message-queue';

    /**
     * @param ServiceLocator<ReceiverInterface> $transportLocator
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $configService,
        #[Autowire(service: 'messenger.receiver_locator')]
        private readonly ServiceLocator $transportLocator,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $maxDiff = $this->configService->getInt('FroshTools.config.monitorQueueGraceTime') ?: 15;
        $oldMessageLimit = (new \DateTimeImmutable())->modify(\sprintf('-%d minutes', $maxDiff));

        $snippet = 'Open Queues';
        $recommended = \sprintf('max %d mins', $maxDiff);

        // 1) Try the Doctrine path first — it gives the richest info (oldest message age).
        //    If the messenger_messages table is missing or the query fails, fall through
        //    to the transport-based path.
        try {
            /** @var string|false $oldestMessageAt */
            $oldestMessageAt = $this->connection->fetchOne('SELECT available_at FROM messenger_messages WHERE available_at < UTC_TIMESTAMP() ORDER BY available_at ASC LIMIT 1');
        } catch (\Doctrine\DBAL\Exception) {
            $oldestMessageAt = false;
        }

        if (\is_string($oldestMessageAt)) {
            $diff = round(abs(
                ((new \DateTime($oldestMessageAt . ' UTC'))->getTimestamp() - $oldMessageLimit->getTimestamp()) / 60,
            ));

            if ($diff > $maxDiff) {
                $result = SettingsResult::warning('queue', $snippet, $diff . ' mins', $recommended);
            } else {
                $result = SettingsResult::ok('queue', $snippet, $diff . ' mins', $recommended);
            }
            $result->url = self::URL;
            $collection->add($result);

            return;
        }

        // 2) Transport-based path: works for any transport that implements
        //    MessageCountAwareInterface (Doctrine with empty table, Redis, …). AMQP does
        //    not implement it in Symfony core and will fall through to the INFO default.
        $collection->add($this->collectFromTransports($snippet, $recommended));
    }

    private function collectFromTransports(string $snippet, string $recommended): SettingsResult
    {
        $totalCount = 0;
        $hasCountableTransport = false;

        try {
            $providedServices = array_keys($this->transportLocator->getProvidedServices());
        } catch (\Throwable) {
            $providedServices = [];
        }

        foreach ($providedServices as $name) {
            if (!\is_string($name) || !str_starts_with($name, 'messenger.transport')) {
                continue;
            }

            try {
                $transport = $this->transportLocator->get($name);
            } catch (\Throwable) {
                // A transport might fail to instantiate if its backend is temporarily
                // unreachable — don't let that kill the whole health check.
                continue;
            }

            if (!$transport instanceof MessageCountAwareInterface) {
                continue;
            }

            $hasCountableTransport = true;

            try {
                $totalCount += $transport->getMessageCount();
            } catch (\Throwable) {
                // Backend hiccup — skip this transport but keep going.
            }
        }

        if (!$hasCountableTransport) {
            $result = SettingsResult::info('queue', $snippet, 'not monitorable', $recommended);
        } elseif ($totalCount === 0) {
            $result = SettingsResult::ok('queue', $snippet, '0 pending', $recommended);
        } else {
            // We know the count but not the age of individual messages, so a high count
            // alone is not a reliable "stuck" signal. Report as OK and let the user drill
            // down via the queue list tab if they want more detail.
            $result = SettingsResult::ok('queue', $snippet, $totalCount . ' pending', $recommended);
        }

        $result->url = self::URL;

        return $result;
    }
}
