<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class PhpChecker implements HealthCheckerInterface, CheckerInterface
{
    public function collect(HealthCollection $collection): void
    {
        $this->checkPhp($collection);
        $this->checkMaxExecutionTime($collection);
        $this->checkMemoryLimit($collection);
        $this->checkOpCacheActive($collection);
        $this->checkPcreJitActive($collection);
    }

    private function formatSize(float $size): string
    {
        $base = log($size) / log(1024);
        $suffix = ['', 'k', 'M', 'G', 'T'][floor($base)];

        return (1024 ** ($base - floor($base))) . $suffix;
    }

    private function checkPhp(HealthCollection $collection): void
    {
        $minPhpVersion = '8.2.0';
        $currentPhpVersion = \PHP_VERSION;
        if (version_compare($minPhpVersion, $currentPhpVersion, '>')) {
            $collection->add(
                SettingsResult::warning(
                    'php-version',
                    'PHP Version',
                    $currentPhpVersion,
                    'min ' . $minPhpVersion
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok(
                'php-version',
                'PHP Version',
                $currentPhpVersion,
                'min ' . $minPhpVersion
            )
        );
    }

    private function checkMaxExecutionTime(HealthCollection $collection): void
    {
        $minMaxExecutionTime = 30;
        $currentMaxExecutionTime = (int) \ini_get('max_execution_time');
        if ($currentMaxExecutionTime !== 0 && $currentMaxExecutionTime < $minMaxExecutionTime) {
            $collection->add(
                SettingsResult::error(
                    'php-max-execution',
                    'Max-Execution-Time',
                    (string) $currentMaxExecutionTime,
                    'min ' . $minMaxExecutionTime
                )
            );

            return;
        }

        $collection->add(SettingsResult::ok(
            'php-max-execution',
            'Max-Execution-Time',
            (string) $currentMaxExecutionTime,
            'min ' . $minMaxExecutionTime
        ));
    }

    private function checkMemoryLimit(HealthCollection $collection): void
    {
        $minMemoryLimit = $this->parseQuantity('512m');
        $currentMemoryLimit = \ini_get('memory_limit');

        $currentMemoryLimit = $this->parseQuantity($currentMemoryLimit);
        if ($currentMemoryLimit < $minMemoryLimit) {
            $collection->add(
                SettingsResult::error(
                    'php-memory-limit',
                    'Memory-Limit',
                    $this->formatSize($currentMemoryLimit),
                    'min ' . $this->formatSize($minMemoryLimit)
                )
            );

            return;
        }

        $collection->add(SettingsResult::ok(
            'php-memory-limit',
            'Memory-Limit',
            $this->formatSize($currentMemoryLimit),
            'min ' . $this->formatSize($minMemoryLimit)
        ));
    }

    private function checkOpCacheActive(HealthCollection $collection): void
    {
        $snippet = 'Zend Opcache';

        if (\extension_loaded('Zend OPcache') && \ini_get('opcache.enable')) {
            $collection->add(SettingsResult::ok('zend-opcache', $snippet, 'active', 'active'));

            return;
        }

        $collection->add(SettingsResult::warning('zend-opcache', $snippet, 'not active', 'active'));
    }

    private function checkPcreJitActive(HealthCollection $collection): void
    {
        $snippet = 'PCRE-Jit';

        if (\ini_get('pcre.jit')) {
            $collection->add(SettingsResult::ok('pcre-jit', $snippet, 'active', 'active'));

            return;
        }

        $collection->add(SettingsResult::warning('pcre-jit', $snippet, 'not active', 'active'));
    }

    private function parseQuantity(string $val): float
    {
        //TODO: remove condition and own calculation when min php version is 8.2
        if (\function_exists('ini_parse_quantity')) {
            return (float) \ini_parse_quantity($val);
        }

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
