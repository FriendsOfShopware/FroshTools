<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\HealthChecker\PhpChecker;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Tests\Components\Health\Checker\AbstractCheckerTestCase;

class PhpCheckerTest extends AbstractCheckerTestCase
{
    private PhpChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new PhpChecker();
    }

    public function testCollectAllChecks(): void
    {
        $collection = $this->createHealthCollection();
        $this->checker->collect($collection);

        // Should have 5 checks: PHP version, max execution time, memory limit, opcache, pcre jit
        $this->assertHealthCollectionCount($collection, 5);

        // Check that all expected IDs are present
        $expectedIds = ['php-version', 'php-max-execution', 'php-memory-limit', 'zend-opcache', 'pcre-jit'];
        foreach ($expectedIds as $id) {
            $result = $this->getResultById($collection, $id);
            $this->assertNotNull($result, "Expected to find result with id '$id'");
        }
    }

    public function testPhpVersionCheck(): void
    {
        $collection = $this->createHealthCollection();
        $this->checker->collect($collection);

        $result = $this->getResultById($collection, 'php-version');
        $this->assertNotNull($result);

        // PHP version should be at least 8.2.0 for OK status
        if (version_compare(PHP_VERSION, '8.2.0', '>=')) {
            $this->assertEquals(SettingsResult::GREEN, $result->state);
        } else {
            $this->assertEquals(SettingsResult::WARNING, $result->state);
        }

        $this->assertEquals(PHP_VERSION, $result->current);
        $this->assertEquals('min 8.2.0', $result->recommended);
    }

    public function testMaxExecutionTimeCheck(): void
    {
        $collection = $this->createHealthCollection();
        $this->checker->collect($collection);

        $result = $this->getResultById($collection, 'php-max-execution');
        $this->assertNotNull($result);

        $currentMaxExecution = (int) ini_get('max_execution_time');
        $this->assertEquals((string) $currentMaxExecution, $result->current);
        $this->assertEquals('min 30', $result->recommended);

        // 0 means no limit, which is OK
        if ($currentMaxExecution === 0 || $currentMaxExecution >= 30) {
            $this->assertEquals(SettingsResult::GREEN, $result->state);
        } else {
            $this->assertEquals(SettingsResult::ERROR, $result->state);
        }
    }

    public function testMemoryLimitCheck(): void
    {
        $collection = $this->createHealthCollection();
        $this->checker->collect($collection);

        $result = $this->getResultById($collection, 'php-memory-limit');
        $this->assertNotNull($result);

        // The recommended value should contain '512M'
        $this->assertStringContainsString('512M', $result->recommended);
    }

    public function testOpCacheCheck(): void
    {
        $collection = $this->createHealthCollection();
        $this->checker->collect($collection);

        $result = $this->getResultById($collection, 'zend-opcache');
        $this->assertNotNull($result);

        $opcacheEnabled = extension_loaded('Zend OPcache') && ini_get('opcache.enable');
        
        if ($opcacheEnabled) {
            $this->assertEquals(SettingsResult::GREEN, $result->state);
            $this->assertEquals('active', $result->current);
        } else {
            $this->assertEquals(SettingsResult::WARNING, $result->state);
            $this->assertEquals('not active', $result->current);
        }
        
        $this->assertEquals('active', $result->recommended);
    }

    public function testPcreJitCheck(): void
    {
        $collection = $this->createHealthCollection();
        $this->checker->collect($collection);

        $result = $this->getResultById($collection, 'pcre-jit');
        $this->assertNotNull($result);

        $pcreJitEnabled = (bool) ini_get('pcre.jit');
        
        if ($pcreJitEnabled) {
            $this->assertEquals(SettingsResult::GREEN, $result->state);
            $this->assertEquals('active', $result->current);
        } else {
            $this->assertEquals(SettingsResult::WARNING, $result->state);
            $this->assertEquals('not active', $result->current);
        }
        
        $this->assertEquals('active', $result->recommended);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('memoryQuantityProvider')]
    public function testParseQuantity(string $value, float $expectedBytes): void
    {
        // Use reflection to test the private parseQuantity method
        $reflection = new \ReflectionClass($this->checker);
        $method = $reflection->getMethod('parseQuantity');
        $method->setAccessible(true);

        $result = $method->invoke($this->checker, $value);
        $this->assertEquals($expectedBytes, $result);
    }

    public static function memoryQuantityProvider(): array
    {
        return [
            ['512', 512.0],
            ['512k', 512.0 * 1024],
            ['512K', 512.0 * 1024],
            ['512m', 512.0 * 1024 * 1024],
            ['512M', 512.0 * 1024 * 1024],
            ['1g', 1024.0 * 1024 * 1024],
            ['1G', 1024.0 * 1024 * 1024],
            ['  1G  ', 1024.0 * 1024 * 1024], // with whitespace
        ];
    }
}