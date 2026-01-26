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
        $collection->add(
            SettingsResult::create(
                $currentValue !== '-1' ? 'warning' : 'ok',
                'zend.assertions',
                'PHP value zend.assertions',
                $currentValue,
                '-1',
                $url,
            ),
        );
    }

    private function checkEnableFileOverride(HealthCollection $collection, string $url): void
    {
        $currentValue = $this->iniGetFailover('opcache.enable_file_override');
        $iniFailOver = !\filter_var($currentValue, \FILTER_VALIDATE_BOOL);
        $collection->add(
            SettingsResult::create(
                $iniFailOver ? 'warning' : 'ok',
                'php.opcache.enable_file_override',
                'PHP value opcache.enable_file_override',
                $currentValue,
                '1',
                $url,
            ),
        );
    }

    private function checkInternedStringsBuffer(HealthCollection $collection, string $url): void
    {
        $currentValue = $this->iniGetFailover('opcache.interned_strings_buffer');
        $buffer = (int) $currentValue < 20;
        $collection->add(
            SettingsResult::create(
                $buffer < 20 ? 'warning' : 'ok',
                'php.opcache.interned_strings_buffer',
                'PHP value opcache.interned_strings_buffer',
                $currentValue,
                'min 20',
                $url,
            ),
        );
    }

    private function checkZendDetectUnicode(HealthCollection $collection, string $url): void
    {
        $currentValue = $this->iniGetFailover('zend.detect_unicode');
        $iniFailOver = \filter_var($currentValue, \FILTER_VALIDATE_BOOL);
        $collection->add(
            SettingsResult::create(
                $iniFailOver ? 'warning' : 'ok',
                'php.zend.detect_unicode',
                'PHP value zend.detect_unicode',
                $currentValue,
                '0',
                $url,
            ),
        );
    }

    private function checkRealpathCacheTtl(HealthCollection $collection, string $url): void
    {
        $currentValue = $this->iniGetFailover('realpath_cache_ttl');
        $ttl = (int) $currentValue < 3600;
        $collection->add(
            SettingsResult::create(
                $ttl < 3600 ? 'warning' : 'ok',
                'php.zend.realpath_cache_ttl',
                'PHP value realpath_cache_ttl',
                $currentValue,
                'min 3600',
                $url,
            ),
        );
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
