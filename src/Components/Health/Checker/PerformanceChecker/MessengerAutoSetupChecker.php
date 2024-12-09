<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MessengerAutoSetupChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public function __construct(
        #[Autowire(param: 'env(MESSENGER_TRANSPORT_DSN)')]
        private readonly string $messageTransportDsn,
        #[Autowire(param: 'env(MESSENGER_TRANSPORT_LOW_PRIORITY_DSN)')]
        private readonly string $messageTransportDsnLowPriority,
        #[Autowire(param: 'env(MESSENGER_TRANSPORT_FAILURE_DSN)')]
        private readonly string $messageTransportDsnFailure,
    ) {}

    public function collect(HealthCollection $collection): void
    {
        if ($this->isAutoSetupEnabled($this->messageTransportDsn) || $this->isAutoSetupEnabled($this->messageTransportDsnLowPriority) || $this->isAutoSetupEnabled($this->messageTransportDsnFailure)) {
            $collection->add(
                SettingsResult::info(
                    'messenger-auto-setup',
                    'Messenger auto_setup',
                    'enabled',
                    'disabled',
                    'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#disable-auto-setup',
                ),
            );
        }
    }

    private function isAutoSetupEnabled(string $messageTransportDsn): bool
    {
        $queryParams = \parse_url($messageTransportDsn, \PHP_URL_QUERY);
        
        // Messenger DSN is invalid. Therefore we can't really check
        if ($params === false) {
            return false;
        }
        
        if ($params === null){
        	return true;
        }

        $query = [];
        \parse_str($queryParams, $query);

        $query += ['auto_setup' => true];

        return filter_var($query['auto_setup'], \FILTER_VALIDATE_BOOL);
    }
}
