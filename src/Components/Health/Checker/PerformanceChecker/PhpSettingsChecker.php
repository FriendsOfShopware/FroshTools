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
        $currentValue = \ini_get('assert.active');
        if ($currentValue !== '0') {
            $collection->add(
                SettingsResult::warning('frosh-tools.checker.AssertActiveWarning',
                    $currentValue,
                    '0',
                    $url
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('frosh-tools.checker.AssertActiveGood',
                $currentValue,
                '0',
                $url
            )
        );
    }

    private function checkEnableFileOverride(HealthCollection $collection, string $url): void
    {
        $currentValue = \ini_get('opcache.enable_file_override');
        if ($currentValue !== '1') {
            $collection->add(
                SettingsResult::warning('frosh-tools.checker.EnableFileOverrideWarning',
                    $currentValue,
                    '1',
                    $url
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('frosh-tools.checker.EnableFileOverrideGood',
                $currentValue,
                '1',
                $url
            )
        );
    }

    private function checkInternedStringsBuffer(HealthCollection $collection, string $url): void
    {
        $currentValue = \ini_get('opcache.interned_strings_buffer');
        if ((int) $currentValue < 20) {
            $collection->add(
                SettingsResult::warning('frosh-tools.checker.InternedStringsBufferWarning',
                    $currentValue,
                    'min 20',
                    $url
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('frosh-tools.checker.InternedStringsBufferGood',
                $currentValue,
                'min 20',
                $url
            )
        );
    }

    private function checkZendDetectUnicode(HealthCollection $collection, string $url): void
    {
        $currentValue = \ini_get('zend.detect_unicode');
        if ($currentValue !== '0') {
            $collection->add(
                SettingsResult::warning('frosh-tools.checker.ZendDetectUnicodeWarning',
                    $currentValue,
                    '0',
                    $url
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('frosh-tools.checker.ZendDetectUnicodeGood',
                $currentValue,
                '0',
                $url
            )
        );
    }

    private function checkRealpathCacheTtl(HealthCollection $collection, string $url): void
    {
        $currentValue = \ini_get('realpath_cache_ttl');
        if ((int) $currentValue < 3600) {
            $collection->add(
                SettingsResult::warning('frosh-tools.checker.RealpathCacheTtlWarning',
                    $currentValue,
                    'min 3600',
                    $url
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('frosh-tools.checker.RealpathCacheTtlGood',
                $currentValue,
                'min 3600',
                $url
            )
        );
    }
}
