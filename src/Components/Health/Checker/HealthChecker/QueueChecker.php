<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\Connection;
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

    private const DOCTRINE_TRANSPORT_CLASS = 'Symfony\\Component\\Messenger\\Bridge\\Doctrine\\Transport\\DoctrineTransport';

    /**
     * @param ServiceLocator<ReceiverInterface> $transportLocator
     */
    public function __construct(
        private readonly SystemConfigService $configService,
        #[Autowire(service: 'messenger.receiver_locator')]
        private readonly ServiceLocator $transportLocator,
        #[Autowire(service: 'shopware.increment.gateway.registry')]
        private readonly IncrementGatewayRegistry $incrementGatewayRegistry,
        private readonly Connection $connection,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $maxDiff = $this->configService->getInt('FroshTools.config.monitorQueueGraceTime') ?: 15;
        $snippet = 'Open Queues';

        [$transportCount, $hasCountableTransport, $hasDoctrineTransport] = $this->inspectTransports();
        $incrementerCount = $this->countPendingFromIncrementer();

        // 1) Doctrine is the active transport → we can reliably query messenger_messages
        //    for the oldest pending message and restore the original "age in minutes"
        //    display. The table is the source of truth while Doctrine is live; processed
        //    messages get deleted, so any row we find is actually pending.
        if ($hasDoctrineTransport) {
            $result = $this->doctrineAgeBasedResult($snippet, $maxDiff);
            $result->url = self::URL;
            $collection->add($result);

            return;
        }

        // 2) No Doctrine transport is active, but some count-capable transport is (Redis,
        //    …) — or the Shopware increment gateway has data. Use a count-based display.
        if ($hasCountableTransport || $incrementerCount > 0) {
            $result = $this->countBasedResult($snippet, $transportCount, $incrementerCount);
            $result->url = self::URL;
            $collection->add($result);

            return;
        }

        // 3) Nothing usable (e.g. pure AMQP setup with the increment pool disabled). We
        //    deliberately do NOT fall back to messenger_messages here, because without an
        //    active Doctrine transport any rows we find there are almost certainly stale
        //    leftovers from a previous configuration.
        $result = SettingsResult::info('queue', $snippet, 'not monitorable', 'n/a');
        $result->url = self::URL;
        $collection->add($result);
    }

    /**
     * @return array{int, bool, bool} [transportCount, hasCountableTransport, hasDoctrineTransport]
     */
    private function inspectTransports(): array
    {
        $totalCount = 0;
        $hasCountableTransport = false;
        $hasDoctrineTransport = false;

        try {
            $providedServices = array_keys($this->transportLocator->getProvidedServices());
        } catch (\Throwable) {
            return [0, false, false];
        }

        foreach ($providedServices as $name) {
            if (!\is_string($name) || !str_starts_with($name, 'messenger.transport')) {
                continue;
            }

            try {
                $transport = $this->transportLocator->get($name);
            } catch (\Throwable) {
                // Backend unreachable or misconfigured transport — ignore and keep going.
                continue;
            }

            // is_a() with a string class name returns false if the class isn't loaded,
            // so this is safe even when symfony/doctrine-messenger isn't installed.
            if (\is_a($transport, self::DOCTRINE_TRANSPORT_CLASS)) {
                $hasDoctrineTransport = true;
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

        return [$totalCount, $hasCountableTransport, $hasDoctrineTransport];
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

    private function doctrineAgeBasedResult(string $snippet, int $maxDiff): SettingsResult
    {
        $recommended = \sprintf('max %d mins', $maxDiff);

        try {
            /** @var string|false $oldestMessageAt */
            $oldestMessageAt = $this->connection->fetchOne('SELECT available_at FROM messenger_messages WHERE available_at < UTC_TIMESTAMP() ORDER BY available_at ASC LIMIT 1');
        } catch (\Doctrine\DBAL\Exception) {
            return SettingsResult::info('queue', $snippet, 'not monitorable', 'n/a');
        }

        if (!\is_string($oldestMessageAt)) {
            return SettingsResult::ok('queue', $snippet, '0 mins', $recommended);
        }

        $oldMessageLimit = (new \DateTimeImmutable())->modify(\sprintf('-%d minutes', $maxDiff));
        $diff = round(abs(
            ((new \DateTime($oldestMessageAt . ' UTC'))->getTimestamp() - $oldMessageLimit->getTimestamp()) / 60,
        ));

        if ($diff > $maxDiff) {
            return SettingsResult::warning('queue', $snippet, $diff . ' mins', $recommended);
        }

        return SettingsResult::ok('queue', $snippet, $diff . ' mins', $recommended);
    }

    private function countBasedResult(string $snippet, int $transportCount, int $incrementerCount): SettingsResult
    {
        $recommended = '0 pending';

        // Both sources agree there's nothing pending → healthy.
        if ($transportCount === 0 && $incrementerCount === 0) {
            return SettingsResult::ok('queue', $snippet, '0 pending', $recommended);
        }

        // Transport backend is empty but the Shopware incrementer still tracks something.
        // This is the "stuck / in flight" state: a handler crashed before decrementing,
        // or a long-running handler is currently holding the message. Surface it as INFO
        // so the user knows it's not a clean "pending" state — they can investigate via
        // the queue list tab (which uses the same incrementer) and clear it with the
        // Reset Queue button.
        if ($transportCount === 0 && $incrementerCount > 0) {
            return SettingsResult::info('queue', $snippet, $incrementerCount . ' in flight', $recommended);
        }

        // Transport backend has messages → actively processing. A persistently non-zero
        // count is the real stuck-worker signal; use the queue list tab for details.
        $displayCount = max($transportCount, $incrementerCount);

        return SettingsResult::ok('queue', $snippet, $displayCount . ' pending', $recommended);
    }
}
