<?php

namespace Frosh\Tools\Tests\unit\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\PerformanceChecker\ProductStreamIndexingChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Test\SettingsResultAssertionHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductStreamIndexingChecker::class)]
class ProductStreamIndexingCheckerTest extends TestCase
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function productStreamIndexingProvider(): array
    {
        return [
            'shopware version too low, indexing enabled' => [
                'shopwareVersion' => '6.6.10.4',
                'productStreamIndexingEnabled' => true,
                'expectInfo' => false,
            ],
            'shopware version too low, indexing disabled' => [
                'shopwareVersion' => '6.6.10.4',
                'productStreamIndexingEnabled' => false,
                'expectInfo' => false,
            ],
            'shopware version minimum, indexing enabled' => [
                'shopwareVersion' => '6.6.10.5',
                'productStreamIndexingEnabled' => true,
                'expectInfo' => true,
            ],
            'shopware version minimum, indexing disabled' => [
                'shopwareVersion' => '6.6.10.5',
                'productStreamIndexingEnabled' => false,
                'expectInfo' => false,
            ],
            'shopware version higher, indexing enabled' => [
                'shopwareVersion' => '6.7.0.0',
                'productStreamIndexingEnabled' => true,
                'expectInfo' => true,
            ],
            'shopware version higher, indexing disabled' => [
                'shopwareVersion' => '6.7.0.0',
                'productStreamIndexingEnabled' => false,
                'expectInfo' => false,
            ],
        ];
    }

    #[DataProvider('productStreamIndexingProvider')]
    public function testCollect(string $shopwareVersion, bool $productStreamIndexingEnabled, bool $expectInfo): void
    {
        // Create the checker with our test configuration
        $checker = new ProductStreamIndexingChecker($productStreamIndexingEnabled, $shopwareVersion);

        // Create a health collection
        $collection = new HealthCollection();

        // Call the collect method
        $checker->collect($collection);

        if ($expectInfo) {
            // Assert that an info message was added to the collection
            SettingsResultAssertionHelper::assertSingleSettingsResult(
                $collection,
                'product-stream-indexing',
                SettingsResult::INFO,
                'enabled',
                'disabled',
                'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#disable-product-stream-indexer'
            );
        } else {
            // Assert that no info message was added to the collection
            SettingsResultAssertionHelper::assertSettingsResultCount($collection, 0);
        }
    }
}
