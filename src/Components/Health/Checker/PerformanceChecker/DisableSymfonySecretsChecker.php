<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DisableSymfonySecretsChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public function __construct(
        #[Autowire(param: 'framework.secrets.enabled')]
        private readonly bool $secretsEnabled,
    ) {}

    public function collect(HealthCollection $collection): void
    {
        if ($this->secretsEnabled) {
            $collection->add(
                SettingsResult::info(
                    'symfony-secrets',
                    'Disable Symfony Secrets',
                    'enabled',
                    'disabled',
                    'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#disable-symfony-secrets',
                ),
            );
        }
    }
}
