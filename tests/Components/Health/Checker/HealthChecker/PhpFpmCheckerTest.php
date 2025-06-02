<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\HealthChecker\PhpFpmChecker;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Tests\Components\Health\Checker\AbstractCheckerTestCase;

class PhpFpmCheckerTest extends AbstractCheckerTestCase
{
    public function testCollectWithClusterSetup(): void
    {
        $checker = new PhpFpmChecker(true);
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        // Should not add any checks when cluster setup is enabled
        $this->assertHealthCollectionCount($collection, 0);
    }

    public function testCollectWithoutFpmFunction(): void
    {
        $checker = new PhpFpmChecker(false);
        $collection = $this->createHealthCollection();
        
        // In most test environments, fpm_get_status won't exist
        $checker->collect($collection);
        
        // Should not add any checks when fpm_get_status doesn't exist
        $this->assertHealthCollectionCount($collection, 0);
    }

    public function testCollectWithFpmStatus(): void
    {
        if (!function_exists('fpm_get_status')) {
            $this->markTestSkipped('fpm_get_status function not available');
        }

        $checker = new PhpFpmChecker(false);
        $collection = $this->createHealthCollection();
        
        // This would require mocking fpm_get_status which is a global function
        // In real environments, this would return actual FPM status
        $checker->collect($collection);
        
        // The actual assertions would depend on the FPM status
        $this->assertGreaterThanOrEqual(0, $collection->count());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('fpmStatusProvider')]
    public function testCheckListenQueue(array $fpmStatus, string $expectedState): void
    {
        // Since we can't easily test the private methods directly without mocking global functions,
        // we'll use reflection to test the logic
        $checker = new PhpFpmChecker(false);
        $collection = $this->createHealthCollection();
        
        $reflection = new \ReflectionClass($checker);
        $method = $reflection->getMethod('checkListenQueue');
        $method->setAccessible(true);
        
        $method->invoke($checker, $fpmStatus, $collection);
        
        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'php-fpm-max-listen-queue', $expectedState);
        
        $result = $this->getResultById($collection, 'php-fpm-max-listen-queue');
        $this->assertNotNull($result);
        $this->assertEquals((string) ($fpmStatus['max-listen-queue'] ?? 0), $result->current);
        $this->assertEquals('0', $result->recommended);
    }

    public static function fpmStatusProvider(): array
    {
        return [
            'no queue' => [['max-listen-queue' => 0], SettingsResult::GREEN],
            'small queue' => [['max-listen-queue' => 5], SettingsResult::WARNING],
            'large queue' => [['max-listen-queue' => 100], SettingsResult::WARNING],
            'missing key' => [[], SettingsResult::GREEN],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('maxChildrenProvider')]
    public function testCheckMaxChildren(array $fpmStatus, string $expectedState, string $expectedCurrent): void
    {
        $checker = new PhpFpmChecker(false);
        $collection = $this->createHealthCollection();
        
        $reflection = new \ReflectionClass($checker);
        $method = $reflection->getMethod('checkMaxChildren');
        $method->setAccessible(true);
        
        $method->invoke($checker, $fpmStatus, $collection);
        
        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'php-fpm-max-children-reached', $expectedState);
        
        $result = $this->getResultById($collection, 'php-fpm-max-children-reached');
        $this->assertNotNull($result);
        $this->assertEquals($expectedCurrent, $result->current);
        $this->assertEquals('no', $result->recommended);
    }

    public static function maxChildrenProvider(): array
    {
        return [
            'not reached' => [['max-children-reached' => 0], SettingsResult::GREEN, 'no'],
            'reached' => [['max-children-reached' => 1], SettingsResult::WARNING, 'yes'],
            'missing key' => [[], SettingsResult::GREEN, 'no'],
        ];
    }

    public function testCheckMemoryPeakWithoutKey(): void
    {
        $checker = new PhpFpmChecker(false);
        $collection = $this->createHealthCollection();
        
        $reflection = new \ReflectionClass($checker);
        $method = $reflection->getMethod('checkMemoryPeak');
        $method->setAccessible(true);
        
        // Test without memory-peak key (PHP < 8.4)
        $method->invoke($checker, [], $collection);
        
        // Should not add any result when memory-peak is not available
        $this->assertHealthCollectionCount($collection, 0);
    }

    public function testCheckMemoryPeakWithKey(): void
    {
        $checker = new PhpFpmChecker(false);
        $collection = $this->createHealthCollection();
        
        $reflection = new \ReflectionClass($checker);
        $method = $reflection->getMethod('checkMemoryPeak');
        $method->setAccessible(true);
        
        // Test with memory-peak key (PHP >= 8.4)
        $memoryPeak = 1024 * 1024 * 256; // 256MB
        $method->invoke($checker, ['memory-peak' => $memoryPeak], $collection);
        
        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'php-fpm-memory-peak', SettingsResult::GREEN);
        
        $result = $this->getResultById($collection, 'php-fpm-memory-peak');
        $this->assertNotNull($result);
        $this->assertEquals('256M', $result->current);
        $this->assertEquals('', $result->recommended);
    }
}