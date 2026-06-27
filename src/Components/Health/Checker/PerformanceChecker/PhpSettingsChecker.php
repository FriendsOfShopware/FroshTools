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
        $url = 'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks#php-config-tweaks';
        $this->checkAssertActive($collection, $url);
        $this->checkEnableFileOverride($collection, $url);
        $this->checkInternedStringsBuffer($collection, $url);
        $this->checkZendDetectUnicode($collection, $url);
        $this->checkRealpathCacheTtl($collection, $url);
    }

    private function checkAssertActive(HealthCollection $collection, string $url): void
    {
        $currentValue = $this->iniGetFailover('zend.assertions');
        if ($currentValue !== '-1') {
            $collection->add(
                SettingsResult::warning(
                    'zend.assertions',
                    'PHP value zend.assertions',
                    $currentValue,
                    '-1',
                    $url,
                ),
            );
        }
    }

    private function checkEnableFileOverride(HealthCollection $collection, string $url): void
    {
        if (!$this->isIniEnabled('opcache.enable_file_override')) {
            $collection->add(
                SettingsResult::warning(
                    'php.opcache.enable_file_override',
                    'PHP value opcache.enable_file_override',
                    $this->iniGetFailover('opcache.enable_file_override'),
                    '1',
                    $url,
                ),
            );
        }
    }

    private function checkInternedStringsBuffer(HealthCollection $collection, string $url): void
    {
        $currentValue = $this->iniGetFailover('opcache.interned_strings_buffer');
        if ((int) $currentValue < 20) {
            $collection->add(
                SettingsResult::warning(
                    'php.opcache.interned_strings_buffer',
                    'PHP value opcache.interned_strings_buffer',
                    $currentValue,
                    'min 20',
                    $url,
                ),
            );
        }
    }

    private function checkZendDetectUnicode(HealthCollection $collection, string $url): void
    {
        if ($this->isIniEnabled('zend.detect_unicode')) {
            $collection->add(
                SettingsResult::warning(
                    'php.zend.detect_unicode',
                    'PHP value zend.detect_unicode',
                    $this->iniGetFailover('zend.detect_unicode'),
                    '0',
                    $url,
                ),
            );
        }
    }

    private function checkRealpathCacheTtl(HealthCollection $collection, string $url): void
    {
        $currentValue = $this->iniGetFailover('realpath_cache_ttl');
        if ((int) $currentValue < 3600) {
            $collection->add(
                SettingsResult::warning(
                    'php.zend.realpath_cache_ttl',
                    'PHP value realpath_cache_ttl',
                    $currentValue,
                    'min 3600',
                    $url,
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
