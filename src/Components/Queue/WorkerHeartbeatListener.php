<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Queue;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * Records per-transport worker heartbeats so the admin can tell whether anyone is
 * actually consuming a queue. WorkerRunningEvent fires on every worker loop iteration,
 * so writes are throttled.
 */
#[AsEventListener]
class WorkerHeartbeatListener
{
    public const CACHE_KEY = 'frosh-tools-worker-heartbeat';

    private const WRITE_INTERVAL = 15;
    private const TTL = 86400;

    private int $lastWrite = 0;

    public function __construct(
        #[Autowire(service: 'cache.object')]
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function __invoke(WorkerRunningEvent $event): void
    {
        $now = time();
        if ($now - $this->lastWrite < self::WRITE_INTERVAL) {
            return;
        }
        $this->lastWrite = $now;

        $item = $this->cache->getItem(self::CACHE_KEY);
        $heartbeats = $item->isHit() && \is_array($item->get()) ? $item->get() : [];

        foreach ($event->getWorker()->getMetadata()->getTransportNames() as $transportName) {
            $heartbeats[$transportName] = $now;
        }

        $item->set($heartbeats);
        $item->expiresAfter(self::TTL);
        $this->cache->save($item);
    }
}
