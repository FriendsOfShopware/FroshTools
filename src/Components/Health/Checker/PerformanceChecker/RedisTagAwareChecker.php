<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\CacheAdapter;
use Frosh\Tools\Components\CacheRegistry;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

/**
 * Recommends RedisTagAware for the HTTP cache pool only.
 *
 * Shopware's HTTP cache relies on tag-based invalidation. The plain Redis adapter
 * stores tags inefficiently; RedisTagAwareAdapter is the recommended backend.
 *
 * Other pools (e.g. cache.object) may intentionally use plain Redis — this checker
 * must not warn about those. See https://github.com/FriendsOfShopware/FroshTools/issues/245
 */
class RedisTagAwareChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly CacheRegistry $cacheRegistry,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        // HTTP cache pool only — not cache.object / cache.app / system.
        if (!$this->cacheRegistry->has('cache.http')) {
            return;
        }

        $httpCacheType = $this->cacheRegistry->get('cache.http')->getType();

        // Already TagAware, or not Redis at all (filesystem, array, …).
        if (!\str_starts_with($httpCacheType, CacheAdapter::TYPE_REDIS)
            || \str_starts_with($httpCacheType, CacheAdapter::TYPE_REDIS_TAG_AWARE)) {
            return;
        }

        $collection->add(
            SettingsResult::warning(
                'redis-tag-aware',
                'HTTP cache (cache.http) Redis adapter should be TagAware',
                $httpCacheType,
                CacheAdapter::TYPE_REDIS_TAG_AWARE,
            ),
        );
    }
}
