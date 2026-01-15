<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Bundle\FrameworkBundle\Secrets\AbstractVault;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DisableSymfonySecretsChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public function __construct(
        #[Autowire(service: 'secrets.vault')]
        private readonly ?AbstractVault $vault = null,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        if ($this->vault) {
            $collection->add(
                SettingsResult::info(
                    'symfony-secrets',
                    'Disable Symfony Secrets',
                    'enabled',
                    'disabled',
                    'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#disable-symfony-secrets',
                ),
            );
        } else {
            $collection->add(
                SettingsResult::ok(
                    'symfony-secrets',
                    'Disable Symfony Secrets',
                    'disabled',
                    'disabled',
                    'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#disable-symfony-secrets',
                ),
            );
        }
    }
}
