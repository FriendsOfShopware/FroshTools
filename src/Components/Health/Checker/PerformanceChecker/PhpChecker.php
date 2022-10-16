<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class PhpChecker implements CheckerInterface
{
    public function collect(HealthCollection $collection): void
    {
        $minPhpVersion = '8.0.0';
        $currentPhpVersion = \PHP_VERSION;
        if (version_compare($minPhpVersion, $currentPhpVersion, '>')) {
            $collection->add(
                SettingsResult::warning('PHP Version is outdated',
                    $currentPhpVersion,
                    'min ' . $minPhpVersion
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('PHP Version',
                $currentPhpVersion,
                'min ' . $minPhpVersion
            )
        );
    }
}
