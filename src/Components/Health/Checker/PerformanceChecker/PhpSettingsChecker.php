<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class PhpSettingsChecker implements CheckerInterface
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
        $currentValue = $this->iniGetFailover('assert.active');
        if ($currentValue !== '0') {
            $collection->add(
                SettingsResult::warning('assert.active', 'PHP value assert.active is not disabled',
                    $currentValue,
                    '0',
                    $url
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('assert.active', 'PHP value assert.active is disabled',
                $currentValue,
                '0',
                $url
            )
        );
    }

    private function checkEnableFileOverride(HealthCollection $collection, string $url): void
    {
        $currentValue = $this->iniGetFailover('opcache.enable_file_override');
        if ($currentValue !== '1') {
            $collection->add(
                SettingsResult::warning('php.opcache.enable_file_override', 'PHP value opcache.enable_file_override is not enabled',
                    $currentValue,
                    '1',
                    $url
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('php.opcache.enable_file_override', 'PHP value opcache.enable_file_override is enabled',
                $currentValue,
                '1',
                $url
            )
        );
    }

    private function checkInternedStringsBuffer(HealthCollection $collection, string $url): void
    {
        $currentValue = $this->iniGetFailover('opcache.interned_strings_buffer');
        if ((int) $currentValue < 20) {
            $collection->add(
                SettingsResult::warning('php.opcache.interned_strings_buffer', 'PHP value opcache.interned_strings_buffer is too low',
                    $currentValue,
                    'min 20',
                    $url
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('php.opcache.interned_strings_buffer', 'PHP value opcache.interned_strings_buffer has minimum value',
                $currentValue,
                'min 20',
                $url
            )
        );
    }

    private function checkZendDetectUnicode(HealthCollection $collection, string $url): void
    {
        $currentValue = $this->iniGetFailover('zend.detect_unicode');
        if ($currentValue !== '0') {
            $collection->add(
                SettingsResult::warning('php.zend.detect_unicode', 'PHP value zend.detect_unicode is not disabled',
                    (string) $currentValue,
                    '0',
                    $url
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('php.zend.detect_unicode', 'PHP value zend.detect_unicode is disabled',
                $currentValue,
                '0',
                $url
            )
        );
    }

    private function checkRealpathCacheTtl(HealthCollection $collection, string $url): void
    {
        $currentValue = $this->iniGetFailover('realpath_cache_ttl');
        if ((int) $currentValue < 3600) {
            $collection->add(
                SettingsResult::warning('php.zend.realpath_cache_ttl', 'PHP value realpath_cache_ttl is low',
                    $currentValue,
                    'min 3600',
                    $url
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('php.zend.realpath_cache_ttl', 'PHP value realpath_cache_ttl is good',
                $currentValue,
                'min 3600',
                $url
            )
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
