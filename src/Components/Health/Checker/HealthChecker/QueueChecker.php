<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

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
        private readonly SystemConfigService $configService,
        #[Autowire(service: 'messenger.receiver_locator')]
        private readonly ServiceLocator $transportLocator,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $maxDiff = $this->configService->getInt('FroshTools.config.monitorQueueGraceTime') ?: 15;
        $snippet = 'Open Queues';
        $recommended = \sprintf('max %d mins', $maxDiff);

        // Always prefer transport-based counting. This is the source of truth for the
        // currently configured transports and bypasses stale rows that may still be
        // sitting in the messenger_messages table from a previous Doctrine configuration.
        //
        // Symfony's DoctrineTransport, RedisTransport, and others all implement
        // MessageCountAwareInterface, so "active" messages always show up here regardless
        // of the underlying backend.
        [$totalCount, $hasCountableTransport] = $this->countPendingFromTransports();

        if ($hasCountableTransport) {
            if ($totalCount === 0) {
                $result = SettingsResult::ok('queue', $snippet, '0 pending', $recommended);
            } else {
                // We know the count but not the age. A persistently non-zero count is
                // the actual "stuck queue" signal; the user can drill down via the queue
                // list tab if they need per-transport details.
                $result = SettingsResult::ok('queue', $snippet, $totalCount . ' pending', $recommended);
            }

            $result->url = self::URL;
            $collection->add($result);

            return;
        }

        // No transport in the locator supports counting (e.g. pure AMQP setup). We
        // deliberately do NOT fall back to reading messenger_messages, because any rows
        // we find there are almost certainly leftover from a previous configuration and
        // would produce a misleading warning.
        $result = SettingsResult::info('queue', $snippet, 'not monitorable', $recommended);
        $result->url = self::URL;
        $collection->add($result);
    }

    /**
     * @return array{int, bool} [totalCount, hasCountableTransport]
     */
    private function countPendingFromTransports(): array
    {
        $totalCount = 0;
        $hasCountableTransport = false;

        try {
            $providedServices = array_keys($this->transportLocator->getProvidedServices());
        } catch (\Throwable) {
            return [0, false];
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

        return [$totalCount, $hasCountableTransport];
    }
}
