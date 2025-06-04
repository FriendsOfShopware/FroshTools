<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DisableAppUrlExternalCheckChecker implements PerformanceCheckerInterface, CheckerInterface
{

    public function __construct(
        #[Autowire('%env(default::bool:APP_URL_CHECK_DISABLED)%')]
        private readonly ?bool $appUrlCheckDisabled,
    )
    {
    }

    public function collect(HealthCollection $collection): void
    {
        if (!$this->appUrlCheckDisabled) {
            $collection->add(
                SettingsResult::warning(
                    'app-url-check-disabled',
                    'App URL external check',
                    'enabled',
                    'disabled',
                    'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#disable-app-url-external-check',
                ),
            );
        }
    }
}
