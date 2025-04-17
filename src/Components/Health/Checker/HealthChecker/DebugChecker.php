<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DebugChecker implements HealthCheckerInterface, CheckerInterface
{
    public function __construct(
        /** @var array<string, string> $kernelBundles */
        #[Autowire(param: 'kernel.bundles')]
        private readonly array $kernelBundles,
        #[Autowire(param: 'kernel.debug')]
        private readonly bool $kernelDebug,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $this->checkWebProfiler($collection);
        $this->checkKernelDebug($collection);
    }

    private function checkWebProfiler(HealthCollection $collection): void
    {
        // @phpstan-ignore-next-line
        if (\in_array(WebProfilerBundle::class, $this->kernelBundles, true)) {
            $collection->add(SettingsResult::error(
                'webprofiler',
                'WebProfilerBundle is active which leaks sensitive information',
                'active',
                'not active',
            ));

            return;
        }

        $collection->add(SettingsResult::ok(
            'webprofiler',
            'WebProfilerBundle is not active',
            'not active',
            'not active',
        ));
    }

    private function checkKernelDebug(HealthCollection $collection): void
    {
        if ($this->kernelDebug) {
            $collection->add(SettingsResult::error(
                'kerneldebug',
                'Kernel debug is active',
                'active',
                'not active',
            ));

            return;
        }

        $collection->add(SettingsResult::ok(
            'kerneldebug',
            'Kernel debug is not active',
            'not active',
            'not active',
        ));
    }
}
