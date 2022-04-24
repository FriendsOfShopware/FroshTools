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
                SettingsResult::warning('frosh-tools.checker.phpOutdated',
                    $currentPhpVersion,
                    'min ' . $minPhpVersion
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('frosh-tools.checker.phpGood',
                $currentPhpVersion,
                'min ' . $minPhpVersion
            )
        );
    }
}
