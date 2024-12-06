<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Shopware\Core\Framework\Adapter\Doctrine\Messenger\DoctrineTransportFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;

class DoctrineMessengerAutoSetupChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public function __construct(
        #[Autowire(service: 'messenger.transport.doctrine.factory')]
        private readonly DoctrineTransportFactory $doctrineTransportFactory,
        #[Autowire(param: 'env(MESSENGER_TRANSPORT_DSN)')]
        private readonly string $messageTransportDsn,
        #[Autowire(param: 'env(MESSENGER_TRANSPORT_LOW_PRIORITY_DSN)')]
        private readonly string $messageTransportDsnLowPriority,
        #[Autowire(param: 'env(MESSENGER_TRANSPORT_FAILURE_DSN)')]
        private readonly string $messageTransportDsnFailure,
    ) {}

    public function collect(HealthCollection $collection): void
    {
        if ($this->isAutoSetupEnabled($this->messageTransportDsn) || $this->isAutoSetupEnabled($this->messageTransportDsnLowPriority) || $this->isAutoSetupEnabled($this->messageTransportDsnFailure) ) {
            $collection->add(
                SettingsResult::info(
                    'doctrine-messenger-auto-setup',
                    'Doctrine messenger auto_setup',
                    'enabled',
                    'disabled',
                    'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#disable-auto-setup',
                ),
            );
        }
    }

    private function isAutoSetupEnabled(string $messageTransportDsn): bool
    {
        if (!$this->doctrineTransportFactory->supports($messageTransportDsn, [])) {
            return false;
        }

        $configuration = Connection::buildConfiguration($messageTransportDsn);

        return !isset($configuration['auto_setup']) || $configuration['auto_setup'] !== false;
    }
}
