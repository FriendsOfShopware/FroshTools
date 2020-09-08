<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker;

use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\HealthResult;

class PhpChecker implements CheckerInterface
{
    public function collect(HealthCollection $collection): void
    {
        if (version_compare('7.4.0', PHP_VERSION, '>')) {
            $collection->add(HealthResult::error('frosh-tools.health.php-version'));
        }

        if ($this->decodePhpSize(ini_get('memory_limit')) < $this->decodePhpSize('512m')) {
            $collection->add(HealthResult::error('frosh-tools.health.php-memory'));
        }
    }

    private function decodePhpSize($val): float
    {
        $val = mb_strtolower(trim($val));
        $last = mb_substr($val, -1);

        $val = (float) $val;
        switch ($last) {
            /* @noinspection PhpMissingBreakStatementInspection */
            case 'g':
                $val *= 1024;
            /* @noinspection PhpMissingBreakStatementInspection */
            // no break
            case 'm':
                $val *= 1024;
            // no break
            case 'k':
                $val *= 1024;
        }

        return $val;
    }
}
