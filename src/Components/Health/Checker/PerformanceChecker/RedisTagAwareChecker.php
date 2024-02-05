<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\CacheAdapter;
use Frosh\Tools\Components\CacheRegistry;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class RedisTagAwareChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly CacheRegistry $cacheRegistry,
        #[Autowire('%kernel.shopware_version%')]
        protected string $shopwareVersion
    ) {}

    public function collect(HealthCollection $collection): void
    {
        $httpCacheType = $this->cacheRegistry->get('cache.http')->getType();

        $redisActive = \str_starts_with($httpCacheType, CacheAdapter::TYPE_REDIS);

        if (!$redisActive) {
            return;
        }

        $redisTagAwareActive = \str_starts_with($httpCacheType, CacheAdapter::TYPE_REDIS_TAG_AWARE);
        $redisTagAwareSupported = \version_compare('6.5.8.3', $this->shopwareVersion, '<=');

        if ($redisTagAwareActive && !$redisTagAwareSupported) {
            $collection->add(
                SettingsResult::warning(
                    'redis-tag-aware-unsupported',
                    'Redis TagAware adapter has issues with your Shopware version',
                    CacheAdapter::TYPE_REDIS_TAG_AWARE,
                    CacheAdapter::TYPE_REDIS,
                    'https://developer.shopware.com/docs/guides/hosting/performance/caches.html#example-replace-some-cache-with-redis'
                )
            );

            return;
        }

        if ($redisTagAwareActive || !$redisTagAwareSupported) {
            return;
        }

        $collection->add(
            SettingsResult::warning(
                'redis-tag-aware',
                'Redis adapter should be TagAware',
                CacheAdapter::TYPE_REDIS,
                CacheAdapter::TYPE_REDIS_TAG_AWARE,
                'https://developer.shopware.com/docs/guides/hosting/performance/caches.html#example-replace-some-cache-with-redis'
            )
        );
    }
}
