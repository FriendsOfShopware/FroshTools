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
        $collection->add(
            SettingsResult::create(
                $this->adminWorkerEnabled ? 'warning' : 'ok',
                'admin-watcher',
                'Admin-Worker',
                $this->adminWorkerEnabled ? 'enabled' : 'disabled',
                'disabled',
                'https://developer.shopware.com/docs/guides/plugins/plugins/framework/message-queue/add-message-handler#the-admin-worker',
            ),
        );
    }
}
