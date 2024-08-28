<?php

declare(strict_types=1);

namespace Frosh\Tools\Task;

use Frosh\Tools\Components\CacheRegistry;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: RedisPrefixCleanupTask::class)]
class RedisPrefixCleanupTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly CacheRegistry $cacheRegistry,
        #[Autowire('%shopware.cache.redis_prefix%')]
        private readonly string $redisPrefix,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        if ($this->redisPrefix === '') {
            return;
        }

        try {
            $redis = $this->cacheRegistry->get('cache.http')->getRedis();
        } catch (\Throwable) {
            return;
        }

        $cursor = null;
        $count = 50;
        $deleteKeys = [];

        do {
            $keys = $redis->scan($cursor, '*', $count);

            if (!\is_array($keys)) {
                return;
            }

            foreach ($keys as $key) {
                if (\str_starts_with($key, $this->redisPrefix)) {
                    continue;
                }

                /**
                 * there could be shared database with session, cart or others,
                 * so we need to make sure that we only delete keys with no ttl
                 */
                if ($redis->ttl($key) !== -1) {
                    continue;
                }

                $deleteKeys[] = $key;
            }
        } while ($cursor !== 0);

        foreach (\array_chunk($deleteKeys, $count) as $chunk) {
            $redis->del($chunk);
        }
    }
}
