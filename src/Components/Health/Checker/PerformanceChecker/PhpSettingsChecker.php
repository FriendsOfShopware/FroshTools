<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\IniReader;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Components\Health\UnexpectedIniValueException;

class PhpSettingsChecker implements PerformanceCheckerInterface, CheckerInterface
{

    private const DOCS_URL = 'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks#php-config-tweaks';

    public function __construct(
        private readonly IniReader $iniReader
    )
    {
    }

    public function collect(HealthCollection $collection): void
    {
        $this->checkAssertActive($collection);
        $this->checkEnableFileOverride($collection);
        $this->checkInternedStringsBuffer($collection);
        $this->checkZendDetectUnicode($collection);
        $this->checkRealpathCacheTtl($collection);
    }

    public function checkAssertActive(HealthCollection $collection): void
    {
        try {
            $currentValue = $this->iniReader->getInt('zend.assertions');
        } catch (UnexpectedIniValueException $e) {
            $currentValue = $e->getActualValue();
        }
        if ($currentValue !== -1) {
            $collection->add(
                SettingsResult::warning(
                    'zend.assertions',
                    'PHP value zend.assertions',
                    (string) $currentValue,
                    '-1',
                    self::DOCS_URL,
                ),
            );
        }
    }

    public function checkEnableFileOverride(HealthCollection $collection): void
    {
        $opcacheFileOverrideEnabled = $this->iniReader->getBoolean('opcache.enable_file_override');
        if (!$opcacheFileOverrideEnabled) {
            $collection->add(
                SettingsResult::warning(
                    'php.opcache.enable_file_override',
                    'PHP value opcache.enable_file_override',
                    (string) $opcacheFileOverrideEnabled,
                    '1',
                    self::DOCS_URL,
                ),
            );
        }
    }

    public function checkInternedStringsBuffer(HealthCollection $collection): void
    {
        try {
            $currentValue = $this->iniReader->getInt('opcache.interned_strings_buffer');
        } catch (UnexpectedIniValueException) {
            $currentValue = null;
        }
        if ($currentValue === null || $currentValue < 20) {
            $collection->add(
                SettingsResult::warning(
                    'php.opcache.interned_strings_buffer',
                    'PHP value opcache.interned_strings_buffer',
                    (string) $currentValue,
                    'min 20',
                    self::DOCS_URL,
                ),
            );
        }
    }

    public function checkZendDetectUnicode(HealthCollection $collection): void
    {
        $currentValue = $this->iniReader->getBoolean('zend.detect_unicode', true);
        if ($currentValue) {
            $collection->add(
                SettingsResult::warning(
                    'php.zend.detect_unicode',
                    'PHP value zend.detect_unicode',
                    (string) $currentValue,
                    '0',
                    self::DOCS_URL,
                ),
            );
        }
    }

    public function checkRealpathCacheTtl(HealthCollection $collection): void
    {
        try {
            $currentValue = $this->iniReader->getInt('realpath_cache_ttl');
        } catch (UnexpectedIniValueException) {
            $currentValue = null;
        }
        if ($currentValue === null || $currentValue < 3600) {
            $collection->add(
                SettingsResult::warning(
                    'php.zend.realpath_cache_ttl',
                    'PHP value realpath_cache_ttl',
                    (string) $currentValue,
                    'min 3600',
                    self::DOCS_URL,
                ),
            );
        }
    }
}
