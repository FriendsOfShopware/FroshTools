<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AdminWorkerChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public function __construct(
        #[Autowire(param: 'shopware.admin_worker.enable_admin_worker')]
        private readonly bool $adminWorkerEnabled,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        if ($this->adminWorkerEnabled) {
            $collection->add(
                SettingsResult::warning(
                    'admin-watcher',
                    'Admin-Worker',
                    'enabled',
                    'disabled',
                    'https://docs.shopware.com/en/shopware-6-en/tutorials-and-faq/message-queue-and-scheduled-tasks#disable-admin-worker-set-up-cli-worker',
                ),
            );
        }
    }
}
