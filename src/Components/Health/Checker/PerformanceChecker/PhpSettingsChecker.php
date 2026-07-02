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
        $collection->add(
            SettingsResult::create(
                $currentValue !== '-1' ? 'warning' : 'ok',
                'zend.assertions',
                'PHP value zend.assertions',
                $currentValue,
                '-1',
            ),
        );
    }

    private function checkEnableFileOverride(HealthCollection $collection): void
    {
        $currentValue = $this->iniGetFailover('opcache.enable_file_override');
        $iniFailOver = !$this->isIniEnabled('opcache.enable_file_override');
        $collection->add(
            SettingsResult::create(
                $iniFailOver ? 'warning' : 'ok',
                'php.opcache.enable_file_override',
                'PHP value opcache.enable_file_override',
                $currentValue,
                '1',
            ),
        );
    }

    private function checkInternedStringsBuffer(HealthCollection $collection): void
    {
        $currentValue = $this->iniGetFailover('opcache.interned_strings_buffer');
        $bufferTooSmall = (int) $currentValue < 20;
        $collection->add(
            SettingsResult::create(
                $bufferTooSmall ? 'warning' : 'ok',
                'php.opcache.interned_strings_buffer',
                'PHP value opcache.interned_strings_buffer',
                $currentValue,
                'min 20',
            ),
        );
    }

    private function checkZendDetectUnicode(HealthCollection $collection): void
    {
        $currentValue = $this->iniGetFailover('zend.detect_unicode');
        $iniFailOver = $this->isIniEnabled('zend.detect_unicode');
        $collection->add(
            SettingsResult::create(
                $iniFailOver ? 'warning' : 'ok',
                'php.zend.detect_unicode',
                'PHP value zend.detect_unicode',
                $currentValue,
                '0',
            ),
        );
    }

    private function checkRealpathCacheTtl(HealthCollection $collection): void
    {
        $currentValue = $this->iniGetFailover('realpath_cache_ttl');
        $ttlTooLow = (int) $currentValue < 3600;
        $collection->add(
            SettingsResult::create(
                $ttlTooLow ? 'warning' : 'ok',
                'php.zend.realpath_cache_ttl',
                'PHP value realpath_cache_ttl',
                $currentValue,
                'min 3600',
            ),
        );
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
