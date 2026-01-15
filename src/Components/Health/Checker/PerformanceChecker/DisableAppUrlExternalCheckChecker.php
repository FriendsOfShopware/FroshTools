<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;

class DisableAppUrlExternalCheckChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public function collect(HealthCollection $collection): void
    {
        $appUrlCheckDisabled = (bool) EnvironmentHelper::getVariable('APP_URL_CHECK_DISABLED', false);
        if (!$appUrlCheckDisabled) {
            $collection->add(
                SettingsResult::warning(
                    'app-url-check-disabled',
                    'App URL external check',
                    'enabled',
                    'disabled',
                    'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#disable-app-url-external-check',
                ),
            );
        } else {
            $collection->add(
                SettingsResult::ok(
                    'app-url-check-disabled',
                    'App URL external check',
                    'disabled',
                    'disabled',
                    'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#disable-app-url-external-check',
                ),
            );
        }
    }
}
