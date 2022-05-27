<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class IncrementStorageChecker implements CheckerInterface
{
    protected string $userActivity;
    protected string $queueActivity;

    public function __construct(string $userActivity, string $queueActivity)
    {
        $this->userActivity = $userActivity;
        $this->queueActivity = $queueActivity;
    }

    public function collect(HealthCollection $collection): void
    {
        $recommended = 'array or redis';

        if ($this->userActivity === 'mysql' || $this->queueActivity === 'mysql') {
            $collection->add(
                SettingsResult::warning('frosh-tools.checker.incrementStorageIsDB',
                    'mysql',
                    $recommended,
                    'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks#increment-storage'
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('frosh-tools.checker.incrementStorageIsNotDB',
                $this->userActivity,
                $recommended
            )
        );
    }
}
