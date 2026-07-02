<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Checker;

use Frosh\Tools\Components\Security\EndOfLifeService;
use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;
use Symfony\Component\HttpKernel\Kernel;

class SymfonyEolChecker implements SecurityCheckerInterface
{
    public function __construct(
        private readonly EndOfLifeService $endOfLifeService,
    ) {}

    public function collect(SecurityCollection $collection): void
    {
        if (!class_exists(Kernel::class)) {
            $collection->add(SecurityFinding::unknown(
                'symfony-eol',
                SecurityFinding::CATEGORY_RUNTIME,
                'Symfony Version',
                'unknown',
                'Could not determine the Symfony version',
            ));

            return;
        }

        $version = Kernel::VERSION;

        $cycle = $this->endOfLifeService->getCycle('symfony', $version);

        $collection->add(EolFindingFactory::fromCycle(
            'symfony-eol',
            'Symfony Version',
            $version,
            $cycle,
            'https://endoflife.date/symfony',
            'The Symfony version is determined by Shopware. Update Shopware to a newer version to get a supported Symfony release.',
        ));
    }
}
