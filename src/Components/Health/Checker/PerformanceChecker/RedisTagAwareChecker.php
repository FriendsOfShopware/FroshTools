<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\CacheAdapter;
use Frosh\Tools\Components\CacheRegistry;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class RedisTagAwareChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly CacheRegistry $cacheRegistry,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $httpCacheType = $this->cacheRegistry->get('cache.http')->getType();

        // no redis
        if (!\str_starts_with($httpCacheType, CacheAdapter::TYPE_REDIS)) {
            return;
        }
        if(!\str_starts_with($httpCacheType, CacheAdapter::TYPE_REDIS_TAG_AWARE)){
            $collection->add(
                SettingsResult::warning(
                    'redis-tag-aware',
                    'Redis adapter should be TagAware',
                    CacheAdapter::TYPE_REDIS,
                    CacheAdapter::TYPE_REDIS_TAG_AWARE,
                    'https://developer.shopware.com/docs/guides/hosting/performance/caches.html#example-replace-some-cache-with-redis',
                ),
            );
        }else{
            $collection->add(
                SettingsResult::ok(
                    'redis-tag-aware',
                    'Redis adapter is TagAware',
                    CacheAdapter::TYPE_REDIS,
                    CacheAdapter::TYPE_REDIS_TAG_AWARE,
                    'https://developer.shopware.com/docs/guides/hosting/performance/caches.html#example-replace-some-cache-with-redis',
                ),
            );
        }
    }
}
