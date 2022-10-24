<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class PhpChecker implements CheckerInterface
{
    public const PHP_VERSION_NAME = 'PHP Version';
    public const PHP_MAX_EXECUTION_TIME = 'PHP Max-Execution-Time';
    public const PHP_MEMORY_LIMIT = 'PHP Memory-Limit';

    public function collect(HealthCollection $collection): void
    {
        $this->checkPhp($collection);
        $this->checkMaxExecutionTime($collection);
        $this->checkMemoryLimit($collection);
        $this->checkOpCacheActive($collection);
    }

    private function formatSize($size): string
    {
        $base = log($size) / log(1024);
        $suffix = ['', 'k', 'M', 'G', 'T'][floor($base)];

        return (1024 ** ($base - floor($base))) . $suffix;
    }

    private function checkPhp(HealthCollection $collection): void
    {
        $minPhpVersion = '8.1.0';
        $currentPhpVersion = \PHP_VERSION;
        if (version_compare('8.0.0', $currentPhpVersion, '>')) {
            $collection->add(
                SettingsResult::error('php-version',self::PHP_VERSION_NAME, 'PHP Version is outdated',
                    $currentPhpVersion,
                    'min ' . $minPhpVersion
                )
            );
        } elseif (version_compare('8.1.0', $currentPhpVersion, '>')) {
            $collection->add(
                SettingsResult::warning('php-version', self::PHP_VERSION_NAME, 'PHP Version is outdated',
                    $currentPhpVersion,
                    'min ' . $minPhpVersion
                )
            );
        } else {
            $collection->add(
                SettingsResult::ok('php-version', self::PHP_VERSION_NAME, 'PHP Version',
                    $currentPhpVersion,
                    'min ' . $minPhpVersion
                )
            );
        }
    }

    private function checkMaxExecutionTime(HealthCollection $collection): void
    {
        $minMaxExecutionTime = 30;
        $currentMaxExecutionTime = (int) ini_get('max_execution_time');
        if ($currentMaxExecutionTime < $minMaxExecutionTime) {
            $collection->add(
                SettingsResult::error('php-max-execution', self::PHP_MAX_EXECUTION_TIME, 'Max-Execution-Time is too low',
                    (string) $currentMaxExecutionTime,
                    'min ' . $minMaxExecutionTime
                )
            );

            return;
        }

        $collection->add(SettingsResult::ok('php-max-execution', self::PHP_MAX_EXECUTION_TIME, 'Max-Execution-Time',
            (string) $currentMaxExecutionTime,
            'min ' . $minMaxExecutionTime
        ));
    }

    private function checkMemoryLimit(HealthCollection $collection): void
    {
        $minMemoryLimit = $this->decodePhpSize('512m');
        $currentMemoryLimit = $this->decodePhpSize(ini_get('memory_limit'));
        if ($currentMemoryLimit < $minMemoryLimit) {
            $collection->add(
                SettingsResult::error('php-memory-limit', self::PHP_MEMORY_LIMIT, 'Memory-Limit is too low',
                    $this->formatSize($currentMemoryLimit),
                    'min ' . $this->formatSize($minMemoryLimit)
                )
            );

            return;
        }

        $collection->add(SettingsResult::ok('php-memory-limit', self::PHP_MEMORY_LIMIT,'Memory-Limit',
            $this->formatSize($currentMemoryLimit),
            'min ' . $this->formatSize($minMemoryLimit)
        ));
    }

    private function checkOpCacheActive(HealthCollection $collection): void
    {
        if (\extension_loaded('Zend OPcache') && ini_get('opcache.enable')) {
            $collection->add(SettingsResult::ok('php-memory-limit', self::PHP_MEMORY_LIMIT,'Zend Opcache is active', 'active', 'active'));

            return;
        }

        $collection->add(SettingsResult::warning('php-memory-limit', self::PHP_MEMORY_LIMIT,'Zend Opcache is not active', 'not active', 'not active'));
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
