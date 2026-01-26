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
        $collection->add(
            SettingsResult::create(
                $this->vault ? 'info' : 'ok',
                'symfony-secrets',
                'Disable Symfony Secrets',
                $this->vault ? 'enabled' : 'disabled',
                'disabled',
                'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#disable-symfony-secrets',
            ),
        );
    }
}
