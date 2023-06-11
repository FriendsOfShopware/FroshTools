<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class IncrementStorageChecker implements CheckerInterface
{
    public function __construct(
        protected string $userActivity,
        protected string $queueActivity
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $recommended = 'array or redis';

        if ($this->userActivity === 'mysql' || $this->queueActivity === 'mysql') {
            $collection->add(
                SettingsResult::warning(
                    'increment-storage',
                    'Increment storage',
                    'mysql',
                    $recommended,
                    'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks#increment-storage'
                )
            );
        }
    }
}
