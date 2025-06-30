<?php

namespace Frosh\Tools\Tests\unit\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\PerformanceChecker\PhpSettingsChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Test\SettingsResultAssertionHelper;
use Frosh\Tools\Test\StaticIniReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpSettingsChecker::class)]
class PhpSettingsCheckerTest extends TestCase
{

    public static function zendAssertionIniDataProvider(): \Generator
    {
        yield 'zend.assertions=1' => ['iniValue' => 1, 'showError' => true];
        yield 'zend.assertions=0' => ['iniValue' => 0, 'showError' => true];
        yield 'zend.assertions=-1' => ['iniValue' => -1, 'showError' => false];
    }

    #[DataProvider('zendAssertionIniDataProvider')]
    public function testAssertActiveCheck(int $iniValue, bool $showError): void
    {
        $healthCollection = new HealthCollection();
        $settingsChecker = new PhpSettingsChecker(
            new StaticIniReader([
                'zend.assertions' => $iniValue
            ])
        );
        $settingsChecker->checkAssertActive($healthCollection);

        if($showError) {
            SettingsResultAssertionHelper::assertSingleSettingsResult(
                $healthCollection,
                'zend.assertions',
                SettingsResult::WARNING
            );
        }else{
            SettingsResultAssertionHelper::assertSettingsResultNotExists(
                $healthCollection,
                'zend.assertions'
            );
        }
    }

    public function testEnableOpcacheFileOverrideCheck(): void
    {
        $healthCollection = new HealthCollection();
        $settingsChecker = new PhpSettingsChecker(
            new StaticIniReader([
                'opcache.enable_file_override' => 'Off'
            ])
        );

        $settingsChecker->checkEnableFileOverride($healthCollection);
        SettingsResultAssertionHelper::assertSingleSettingsResult(
            $healthCollection,
            'php.opcache.enable_file_override',
            SettingsResult::WARNING
        );

        $healthCollection = new HealthCollection();
        $settingsChecker = new PhpSettingsChecker(
            new StaticIniReader([
                'opcache.enable_file_override' => 'On'
            ])
        );

        $settingsChecker->checkEnableFileOverride($healthCollection);
        SettingsResultAssertionHelper::assertSettingsResultNotExists(
            $healthCollection,
            'php.opcache.enable_file_override'
        );
    }

    public static function internedStringsBufferDataProvider(): \Generator
    {
        yield 'opcache.interned_strings_buffer=10' => ['iniValue' => 10, 'showError' => true];
        yield 'opcache.interned_strings_buffer=19' => ['iniValue' => 19, 'showError' => true];
        yield 'opcache.interned_strings_buffer=20' => ['iniValue' => 20, 'showError' => false];
        yield 'opcache.interned_strings_buffer=30' => ['iniValue' => 30, 'showError' => false];
    }

    #[DataProvider('internedStringsBufferDataProvider')]
    public function testOpcacheInternedStringsBufferChecker(int $iniValue, bool $showError): void
    {
        $healthCollection = new HealthCollection();
        $settingsChecker = new PhpSettingsChecker(
            new StaticIniReader([
                'opcache.interned_strings_buffer' => $iniValue
            ])
        );

        $settingsChecker->checkInternedStringsBuffer($healthCollection);

        if ($showError) {
            SettingsResultAssertionHelper::assertSingleSettingsResult(
                $healthCollection,
                'php.opcache.interned_strings_buffer',
                SettingsResult::WARNING
            );
        } else {
            SettingsResultAssertionHelper::assertSettingsResultNotExists(
                $healthCollection,
                'php.opcache.interned_strings_buffer'
            );
        }
    }

    public function testOpcacheInternedStringsBufferCheckerWithNullValue(): void
    {
        $healthCollection = new HealthCollection();
        $settingsChecker = new PhpSettingsChecker(
            new StaticIniReader([
                'opcache.interned_strings_buffer' => null
            ])
        );

        $settingsChecker->checkInternedStringsBuffer($healthCollection);

        SettingsResultAssertionHelper::assertSingleSettingsResult(
            $healthCollection,
            'php.opcache.interned_strings_buffer',
            SettingsResult::WARNING
        );
    }

    public static function zendDetectUnicodeDataProvider(): \Generator
    {
        yield 'zend.detect_unicode=On' => ['iniValue' => 'On', 'showError' => true];
        yield 'zend.detect_unicode=1' => ['iniValue' => 1, 'showError' => true];
        yield 'zend.detect_unicode=Off' => ['iniValue' => 'Off', 'showError' => false];
        yield 'zend.detect_unicode=0' => ['iniValue' => 0, 'showError' => false];
    }

    #[DataProvider('zendDetectUnicodeDataProvider')]
    public function testZendDetectUnicodeCheck($iniValue, bool $showError): void
    {
        $healthCollection = new HealthCollection();
        $settingsChecker = new PhpSettingsChecker(
            new StaticIniReader([
                'zend.detect_unicode' => $iniValue
            ])
        );

        $settingsChecker->checkZendDetectUnicode($healthCollection);

        if ($showError) {
            SettingsResultAssertionHelper::assertSingleSettingsResult(
                $healthCollection,
                'php.zend.detect_unicode',
                SettingsResult::WARNING
            );
        } else {
            SettingsResultAssertionHelper::assertSettingsResultNotExists(
                $healthCollection,
                'php.zend.detect_unicode'
            );
        }
    }

    public static function realpathCacheTtlDataProvider(): \Generator
    {
        yield 'realpath_cache_ttl=60' => ['iniValue' => 60, 'showError' => true];
        yield 'realpath_cache_ttl=3599' => ['iniValue' => 3599, 'showError' => true];
        yield 'realpath_cache_ttl=3600' => ['iniValue' => 3600, 'showError' => false];
        yield 'realpath_cache_ttl=7200' => ['iniValue' => 7200, 'showError' => false];
    }

    #[DataProvider('realpathCacheTtlDataProvider')]
    public function testRealpathCacheTtlCheck(int $iniValue, bool $showError): void
    {
        $healthCollection = new HealthCollection();
        $settingsChecker = new PhpSettingsChecker(
            new StaticIniReader([
                'realpath_cache_ttl' => $iniValue
            ])
        );

        $settingsChecker->checkRealpathCacheTtl($healthCollection);

        if ($showError) {
            SettingsResultAssertionHelper::assertSingleSettingsResult(
                $healthCollection,
                'php.zend.realpath_cache_ttl',
                SettingsResult::WARNING
            );
        } else {
            SettingsResultAssertionHelper::assertSettingsResultNotExists(
                $healthCollection,
                'php.zend.realpath_cache_ttl'
            );
        }
    }

    public function testRealpathCacheTtlCheckerWithNullValue(): void
    {
        $healthCollection = new HealthCollection();
        $settingsChecker = new PhpSettingsChecker(
            new StaticIniReader([
                'realpath_cache_ttl' => null
            ])
        );

        $settingsChecker->checkRealpathCacheTtl($healthCollection);

        SettingsResultAssertionHelper::assertSingleSettingsResult(
            $healthCollection,
            'php.zend.realpath_cache_ttl',
            SettingsResult::WARNING
        );
    }
}
