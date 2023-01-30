<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;

class FixCacheIdSetChecker implements CheckerInterface
{
    protected string $shopwareVersion;

    public function __construct(string $shopwareVersion)
    {
        $this->shopwareVersion = $shopwareVersion;
    }

    public function collect(HealthCollection $collection): void
    {
        if (\version_compare('6.4.11.0', $this->shopwareVersion, '>')) {
            return;
        }

        $cacheId = (string) EnvironmentHelper::getVariable('SHOPWARE_CACHE_ID', '');

        if ($cacheId === '') {
            $collection->add(
                SettingsResult::warning('cache-id', 'A fixed cache id should be set',
                    'not set',
                    'set',
                    'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks#cache-id'
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('cache-id', 'A fixed cache id is set',
                \sprintf('set (%s)', $cacheId),
                'set',
            )
        );
    }
}
