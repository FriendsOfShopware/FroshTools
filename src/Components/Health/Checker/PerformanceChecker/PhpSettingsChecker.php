<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class PhpSettingsChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public function collect(HealthCollection $collection): void
    {
        $this->checkAssertActive($collection);
        $this->checkEnableFileOverride($collection);
        $this->checkInternedStringsBuffer($collection);
        $this->checkZendDetectUnicode($collection);
        $this->checkRealpathCacheTtl($collection);
    }

    private function checkAssertActive(HealthCollection $collection): void
    {
        $currentValue = $this->iniGetFailover('zend.assertions');
        if ($currentValue !== '-1') {
            $collection->add(
                SettingsResult::warning(
                    'zend.assertions',
                    'PHP value zend.assertions',
                    $currentValue,
                    '-1',
                ),
            );
        }
    }

    private function checkEnableFileOverride(HealthCollection $collection): void
    {
        if (!$this->isIniEnabled('opcache.enable_file_override')) {
            $collection->add(
                SettingsResult::warning(
                    'php.opcache.enable_file_override',
                    'PHP value opcache.enable_file_override',
                    $this->iniGetFailover('opcache.enable_file_override'),
                    '1',
                ),
            );
        } else {
            $collection->add(
                SettingsResult::ok(
                    'php.opcache.enable_file_override',
                    'PHP value opcache.enable_file_override',
                    $currentValue,
                    '1',
                    $url,
                ),
            );
        }
    }

    private function checkInternedStringsBuffer(HealthCollection $collection): void
    {
        $currentValue = $this->iniGetFailover('opcache.interned_strings_buffer');
        if ((int) $currentValue < 20) {
            $collection->add(
                SettingsResult::warning(
                    'php.opcache.interned_strings_buffer',
                    'PHP value opcache.interned_strings_buffer',
                    $currentValue,
                    'min 20',
                ),
            );
        }
    }

    private function checkZendDetectUnicode(HealthCollection $collection): void
    {
        if ($this->isIniEnabled('zend.detect_unicode')) {
            $collection->add(
                SettingsResult::warning(
                    'php.zend.detect_unicode',
                    'PHP value zend.detect_unicode',
                    $this->iniGetFailover('zend.detect_unicode'),
                    '0',
                ),
            );
        }
    }

    private function checkRealpathCacheTtl(HealthCollection $collection): void
    {
        $currentValue = $this->iniGetFailover('realpath_cache_ttl');
        if ((int) $currentValue < 3600) {
            $collection->add(
                SettingsResult::warning(
                    'php.zend.realpath_cache_ttl',
                    'PHP value realpath_cache_ttl',
                    $currentValue,
                    'min 3600',
                ),
            );
        }
    }

    /**
     * Determines whether a boolean ini directive is enabled.
     *
     * ini_get() returns false when a directive is unknown/unavailable and an
     * empty string when it is explicitly disabled (e.g. "Off" or "0"); both
     * cases must be treated as "not enabled". This avoids relying on the
     * "not set" failover placeholder being coincidentally falsy in filter_var().
     */
    private function isIniEnabled(string $option): bool
    {
        $currentValue = \ini_get($option);
        if (!\is_string($currentValue)) {
            return false;
        }

        return \filter_var($currentValue, \FILTER_VALIDATE_BOOL);
    }

    private function iniGetFailover(string $option): string
    {
        $currentValue = \ini_get($option);
        if (\is_string($currentValue)) {
            return $currentValue;
        }

        return 'not set';
    }
}
