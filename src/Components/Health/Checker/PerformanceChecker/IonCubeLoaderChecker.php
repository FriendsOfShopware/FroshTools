<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class IonCubeLoaderChecker implements PerformanceCheckerInterface, CheckerInterface
{
    /**
     * @param \Closure(string): bool|null $extensionLoaded
     */
    public function __construct(
        private readonly ?\Closure $extensionLoaded = null,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $extensionLoaded = $this->extensionLoaded ?? \Closure::fromCallable('extension_loaded');
        if (!$extensionLoaded('ionCube Loader')) {
            return;
        }

        $collection->add(SettingsResult::warning(
            'ioncube-loader',
            'ionCube Loader can drastically decrease performance',
            'enabled',
            'disabled',
        ));
    }
}
