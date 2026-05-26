<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Checker;

use Frosh\Tools\Components\Security\EndOfLifeService;
use Frosh\Tools\Components\Security\SecurityCollection;

class PhpEolChecker implements SecurityCheckerInterface
{
    public function __construct(
        private readonly EndOfLifeService $endOfLifeService,
    ) {
    }

    public function collect(SecurityCollection $collection): void
    {
        $version = \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION;

        $cycle = $this->endOfLifeService->getCycle('php', $version);

        $collection->add(EolFindingFactory::fromCycle(
            'php-eol',
            'PHP Version',
            \PHP_VERSION,
            $cycle,
            'https://endoflife.date/php',
        ));
    }
}
