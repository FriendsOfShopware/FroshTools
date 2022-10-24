<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class AdminWorkerChecker implements CheckerInterface
{
    public const ADMIN_WORKER_CHECKER_NAME = 'Admin-Worker';

    private bool $adminWorkerEnabled;

    public function __construct(bool $adminWorkerEnabled)
    {
        $this->adminWorkerEnabled = $adminWorkerEnabled;
    }

    public function collect(HealthCollection $collection): void
    {
        if ($this->adminWorkerEnabled) {
            $collection->add(
                SettingsResult::warning('admin-watcher', self::ADMIN_WORKER_CHECKER_NAME, 'Admin-Worker should be disabled',
                    'enabled',
                    'disabled',
                    'https://developer.shopware.com/docs/guides/plugins/plugins/framework/message-queue/add-message-handler#the-admin-worker'
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('admin-watcher', self::ADMIN_WORKER_CHECKER_NAME, 'Admin-Worker is disabled',
                'disabled',
                'disabled'
            )
        );
    }
}
