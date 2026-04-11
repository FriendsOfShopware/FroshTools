<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
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
        #[Autowire(service: 'shopware.increment.gateway.registry')]
        private readonly IncrementGatewayRegistry $incrementGatewayRegistry,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $maxDiff = $this->configService->getInt('FroshTools.config.monitorQueueGraceTime') ?: 15;
        $snippet = 'Open Queues';
        $recommended = \sprintf('max %d mins', $maxDiff);

        // Two sources for "how much is pending":
        //
        //  1) messenger.receiver_locator → MessageCountAwareInterface. Source of truth
        //     for the actual state of the configured transport backend(s) (Doctrine,
        //     Redis, …).
        //  2) shopware.increment.gateway.registry → "message_queue" pool, key
        //     "message_queue_stats". Shopware increments this on dispatch and
        //     decrements on handle. A stuck counter here (handler crashed before
        //     decrement) is exactly the state the queue list tab surfaces but the
        //     transport backend no longer reflects.
        //
        // Using the maximum of both makes the Open Queues row agree with the queue
        // list tab: a stuck counter shows up as pending and can be cleared via the
        // "Reset Queue" button in the admin.
        [$transportCount, $hasCountableTransport] = $this->countPendingFromTransports();
        $incrementerCount = $this->countPendingFromIncrementer();
        $displayCount = max($transportCount, $incrementerCount);

        if ($hasCountableTransport || $incrementerCount > 0) {
            if ($displayCount === 0) {
                $result = SettingsResult::ok('queue', $snippet, '0 pending', $recommended);
            } else {
                // We know the count but not the age of individual messages. A
                // persistently non-zero count is the real stuck-queue signal; drill
                // down via the queue list tab for per-message details.
                $result = SettingsResult::ok('queue', $snippet, $displayCount . ' pending', $recommended);
            }
        } else {
            // Neither the transport locator nor the incrementer gave us anything
            // usable (e.g. pure AMQP setup with the increment pool disabled). We do
            // NOT fall back to reading messenger_messages, because any rows there are
            // almost certainly leftovers from a previous configuration and would
            // produce a misleading warning.
            $result = SettingsResult::info('queue', $snippet, 'not monitorable', $recommended);
        }

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

    private function countPendingFromIncrementer(): int
    {
        try {
            $gateway = $this->incrementGatewayRegistry->get('message_queue');
            $list = $gateway->list('message_queue_stats', -1);
        } catch (\Throwable) {
            // Increment pool not configured, backend unreachable, etc.
            return 0;
        }

        $total = 0;
        foreach ($list as $entry) {
            if (\is_array($entry) && isset($entry['count'])) {
                $total += (int) $entry['count'];
            }
        }

        return $total;
    }
}
