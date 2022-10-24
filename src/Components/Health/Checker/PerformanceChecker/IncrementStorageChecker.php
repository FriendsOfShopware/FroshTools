<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class IncrementStorageChecker implements CheckerInterface
{
    public const INCREMENT_STORAGE_NAME = 'Increment storage';

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
                SettingsResult::warning('increment-storage', self::INCREMENT_STORAGE_NAME, 'Increment storage is heavily using the Storage. This feature should be disabled or Redis should be used',
                    'mysql',
                    $recommended,
                    'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks#increment-storage'
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('increment-storage', self::INCREMENT_STORAGE_NAME, 'Increment storage is correct configured',
                $this->userActivity,
                $recommended
            )
        );
    }
}
