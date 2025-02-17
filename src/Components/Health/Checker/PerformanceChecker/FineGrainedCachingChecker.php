<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class FineGrainedCachingChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public const DOCUMENTATION_URL = 'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#disable-fine-grained-caching';

    public function __construct(
        #[Autowire('%kernel.shopware_version%')]
        public readonly string $shopwareVersion,
        #[Autowire('%shopware.cache.tagging.each_config%')]
        public readonly bool $cacheTaggingEachConfig,
        #[Autowire('%shopware.cache.tagging.each_snippet%')]
        public readonly bool $cacheTaggingEachSnippet,
        #[Autowire('%shopware.cache.tagging.each_theme_config%')]
        public readonly bool $cacheTaggingEachThemeConfig,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        if (\version_compare('6.5.4.0', $this->shopwareVersion, '>')) {
            return;
        }

        if ($this->cacheTaggingEachConfig || $this->cacheTaggingEachSnippet || $this->cacheTaggingEachThemeConfig) {
            $collection->add(
                // only info, because it only affects redis, varnish etc.
                SettingsResult::info(
                    'fine-grained-caching',
                    'Fine-grained caching on Redis, Varnish etc.',
                    'enabled',
                    'disabled',
                    self::DOCUMENTATION_URL,
                ),
            );
        }
    }
}
