<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class FixCacheIdSetChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public function __construct(
        #[Autowire('%kernel.shopware_version%')]
        protected string $shopwareVersion
    ) {}

    public function collect(HealthCollection $collection): void
    {
        if (\version_compare('6.4.11.0', $this->shopwareVersion, '>')) {
            return;
        }

        $cacheId = (string) EnvironmentHelper::getVariable('SHOPWARE_CACHE_ID', '');

        if ($cacheId === '') {
            $collection->add(
                SettingsResult::warning(
                    'cache-id',
                    'Fixed cache id',
                    'not set',
                    'set',
                    'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks#cache-id'
                )
            );
        }
    }
}
