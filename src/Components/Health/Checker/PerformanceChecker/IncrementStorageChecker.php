<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class IncrementStorageChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public function __construct(
        #[Autowire(param: 'shopware.increment.user_activity.type')]
        private readonly string $userActivity,
        #[Autowire(param: 'shopware.increment.message_queue.type')]
        private readonly string $queueActivity,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $usesMysql = $this->userActivity === 'mysql' || $this->queueActivity === 'mysql';
        $current = $this->userActivity === $this->queueActivity
            ? $this->userActivity
            : $this->userActivity . ', ' . $this->queueActivity;

        $collection->add(
            SettingsResult::create(
                $usesMysql ? SettingsResult::WARNING : SettingsResult::GREEN,
                'increment-storage',
                'Increment storage',
                $current,
                'array or redis',
            ),
        );
    }
}
