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
        #[Autowire(param: 'framework.secrets.enabled')]
        private readonly bool $secretsEnabled,
        #[Autowire(service: 'secrets.vault')]
        private readonly AbstractVault $vault,
        #[Autowire(service: 'secrets.local_vault')]
        private readonly ?AbstractVault $localVault = null,
    ) {}

    public function collect(HealthCollection $collection): void
    {
        if ($this->secretsEnabled && !$this->areSecretsInUse()) {
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

    private function areSecretsInUse(): bool
    {
        return count($this->vault->list()) > 0 || ($this->localVault instanceof AbstractVault && count($this->localVault->list()) > 0);
    }
}
