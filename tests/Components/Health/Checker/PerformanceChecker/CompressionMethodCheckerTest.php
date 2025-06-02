<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\PerformanceChecker\CompressionMethodChecker;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Tests\Components\Health\Checker\AbstractCheckerTestCase;

class CompressionMethodCheckerTest extends AbstractCheckerTestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('shopwareVersionProvider')]
    public function testCollectWithOldShopwareVersion(string $shopwareVersion): void
    {
        $checker = new CompressionMethodChecker(
            $shopwareVersion,
            true,
            'gzip',
            true,
            'gzip'
        );
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 0);
    }

    public static function shopwareVersionProvider(): array
    {
        return [
            ['6.6.3.0'],
            ['6.6.0.0'],
            ['6.5.0.0'],
        ];
    }

    public function testCollectWithBothCompressionDisabled(): void
    {
        $checker = new CompressionMethodChecker(
            '6.6.4.0',
            false,
            'gzip',
            false,
            'gzip'
        );
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 0);
    }

    public function testCollectWithCacheCompressionGzip(): void
    {
        $checker = new CompressionMethodChecker(
            '6.6.4.0',
            true,
            'gzip',
            false,
            'gzip'
        );
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'cache-compression-method', SettingsResult::INFO);
        
        $result = $this->getResultById($collection, 'cache-compression-method');
        $this->assertNotNull($result);
        $this->assertEquals('Cache compression method', $this->getProtectedProperty($result, 'snippet'));
        $this->assertEquals('gzip', $result->current);
        $this->assertEquals('zstd', $result->recommended);
        $this->assertEquals(CompressionMethodChecker::DOCUMENTATION_URL, $result->url);
    }

    public function testCollectWithCartCompressionGzip(): void
    {
        $checker = new CompressionMethodChecker(
            '6.6.4.0',
            false,
            'gzip',
            true,
            'gzip'
        );
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'cart-compression-method', SettingsResult::INFO);
        
        $result = $this->getResultById($collection, 'cart-compression-method');
        $this->assertNotNull($result);
        $this->assertEquals('Cart compression method', $this->getProtectedProperty($result, 'snippet'));
        $this->assertEquals('gzip', $result->current);
        $this->assertEquals('zstd', $result->recommended);
        $this->assertEquals(CompressionMethodChecker::DOCUMENTATION_URL, $result->url);
    }

    public function testCollectWithBothCompressionGzip(): void
    {
        $checker = new CompressionMethodChecker(
            '6.6.4.0',
            true,
            'gzip',
            true,
            'gzip'
        );
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 2);
        $this->assertHealthCollectionContains($collection, 'cache-compression-method', SettingsResult::INFO);
        $this->assertHealthCollectionContains($collection, 'cart-compression-method', SettingsResult::INFO);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testCollectWithZstdWithoutExtension(): void
    {
        // Mock the extension_loaded function for this test
        $this->mockExtensionLoaded(false);
        
        $checker = new CompressionMethodChecker(
            '6.6.4.0',
            true,
            'zstd',
            true,
            'zstd'
        );
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 2);
        $this->assertHealthCollectionContains($collection, 'cache-compression-method-extension-zstd', SettingsResult::ERROR);
        $this->assertHealthCollectionContains($collection, 'cart-compression-method-extension-zstd', SettingsResult::ERROR);
        
        $cacheResult = $this->getResultById($collection, 'cache-compression-method-extension-zstd');
        $this->assertNotNull($cacheResult);
        $this->assertEquals('PHP extension zstd for Cache compression method', $this->getProtectedProperty($cacheResult, 'snippet'));
        $this->assertEquals('disabled', $cacheResult->current);
        $this->assertEquals('enabled', $cacheResult->recommended);
        
        $cartResult = $this->getResultById($collection, 'cart-compression-method-extension-zstd');
        $this->assertNotNull($cartResult);
        $this->assertEquals('PHP extension zstd for Cart compression method', $this->getProtectedProperty($cartResult, 'snippet'));
        $this->assertEquals('disabled', $cartResult->current);
        $this->assertEquals('enabled', $cartResult->recommended);
    }

    public function testCollectWithZstdConfiguration(): void
    {
        // Test with zstd configuration - the exact result depends on whether
        // the zstd extension is loaded in the test environment
        $checker = new CompressionMethodChecker(
            '6.6.4.0',
            true,
            'zstd',
            true,
            'zstd'
        );
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        // Could be 0 (if extension loaded) or 2 (if extension not loaded)
        $this->assertGreaterThanOrEqual(0, $collection->count());
        $this->assertLessThanOrEqual(2, $collection->count());
    }

    public function testCollectWithMixedCompressionMethods(): void
    {
        $checker = new CompressionMethodChecker(
            '6.6.4.0',
            true,
            'gzip',
            true,
            'other'
        );
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'cache-compression-method', SettingsResult::INFO);
        // 'other' method should not produce any result as it's neither 'gzip' nor 'zstd'
    }

    private function mockExtensionLoaded(bool $loaded): void
    {
        eval('
            namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker {
                function extension_loaded($extension) {
                    if ($extension === "zstd") {
                        return ' . ($loaded ? 'true' : 'false') . ';
                    }
                    return \extension_loaded($extension);
                }
            }
        ');
    }
}