<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\FroshTools;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class PhpFpmChecker implements HealthCheckerInterface, CheckerInterface
{
    public function __construct(
        #[Autowire(param: 'shopware.deployment.cluster_setup')]
        private readonly bool $clusterSetup,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        if ($this->clusterSetup) {
            return;
        }

        if (\function_exists('fpm_get_status') === false) {
            return;
        }

        $fpmStatus = \fpm_get_status();

        if ($fpmStatus === false) {
            return;
        }

        $this->checkListenQueue($fpmStatus, $collection);
        $this->checkMaxChildrenReached($fpmStatus, $collection);
        $this->checkMemoryPeak($fpmStatus, $collection);
    }

    private function checkListenQueue(array $fpmStatus, HealthCollection $collection): void
    {
        $listenQueue = $fpmStatus['max-listen-queue'] ?? 0;

        $status = SettingsResult::ok(
            'php-fpm-max-listen-queue',
            'PHP FPM max listen queue',
            (string) $listenQueue,
            '0',
            'https://www.php.net/manual/en/fpm.status.php#:~:text=a%20free%20process.-,max%20listen%20queue,-The%20maximum%20number'
        );

        if ($listenQueue > 0) {
            $status->state = SettingsResult::INFO;
        }

        $collection->add($status);
    }

    private function checkMaxChildrenReached(array $fpmStatus, HealthCollection $collection): void
    {
        $maxChildrenReached = (int) $fpmStatus['max-children-reached'];

        $status = SettingsResult::ok(
            'php-fpm-max-children-reached',
            'PHP FPM max children reached',
            (string) $maxChildrenReached,
            '0',
            'https://www.php.net/manual/en/fpm.status.php#:~:text=max%20children%20reached'
        );

        if ($maxChildrenReached > 0) {
            $status->state = SettingsResult::INFO;
        }

        $collection->add($status);
    }

    private function checkMemoryPeak(array $fpmStatus, HealthCollection $collection): void
    {
        // Skip this check if memory-peak is not available (PHP < 8.4)
        if (!isset($fpmStatus['memory-peak'])) {
            return;
        }

        $memoryPeak = $fpmStatus['memory-peak'];
        $memoryPeak = FroshTools::formatSize($memoryPeak);

        $collection->add(
            SettingsResult::ok(
                'php-fpm-memory-peak',
                'PHP FPM memory peak',
                $memoryPeak,
                '',
                'https://www.php.net/manual/en/fpm.status.php#:~:text=memory%20peak'
            )
        );
    }
}
