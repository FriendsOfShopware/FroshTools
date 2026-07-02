<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Checker;

use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class EnvironmentChecker implements SecurityCheckerInterface
{
    public function __construct(
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
        #[Autowire(param: 'kernel.debug')]
        private readonly bool $kernelDebug,
        /**
         * @var array<string, string> $kernelBundles
         */
        #[Autowire(param: 'kernel.bundles')]
        private readonly array $kernelBundles,
    ) {
    }

    public function collect(SecurityCollection $collection): void
    {
        $this->checkAppEnv($collection);
        $this->checkKernelDebug($collection);
        $this->checkWebProfiler($collection);
    }

    private function checkAppEnv(SecurityCollection $collection): void
    {
        if ($this->environment !== 'prod') {
            $collection->add(SecurityFinding::high(
                'app-env',
                SecurityFinding::CATEGORY_CONFIGURATION,
                'Application environment',
                $this->environment,
                'Run the shop with APP_ENV=prod to avoid exposing stack traces and internals',
            ));

            return;
        }

        $collection->add(SecurityFinding::ok(
            'app-env',
            SecurityFinding::CATEGORY_CONFIGURATION,
            'Application environment',
            $this->environment,
        ));
    }

    private function checkKernelDebug(SecurityCollection $collection): void
    {
        if ($this->kernelDebug) {
            $collection->add(SecurityFinding::high(
                'kernel-debug',
                SecurityFinding::CATEGORY_CONFIGURATION,
                'Debug mode',
                'active',
                'Disable debug mode (APP_DEBUG=0) in production',
            ));

            return;
        }

        $collection->add(SecurityFinding::ok(
            'kernel-debug',
            SecurityFinding::CATEGORY_CONFIGURATION,
            'Debug mode',
            'disabled',
        ));
    }

    private function checkWebProfiler(SecurityCollection $collection): void
    {
        // @phpstan-ignore-next-line
        if (\in_array(WebProfilerBundle::class, $this->kernelBundles, true)) {
            $collection->add(SecurityFinding::critical(
                'web-profiler',
                SecurityFinding::CATEGORY_CONFIGURATION,
                'Web profiler',
                'active',
                'The WebProfilerBundle leaks sensitive information and must not be active in production',
            ));

            return;
        }

        $collection->add(SecurityFinding::ok(
            'web-profiler',
            SecurityFinding::CATEGORY_CONFIGURATION,
            'Web profiler',
            'not active',
        ));
    }
}
