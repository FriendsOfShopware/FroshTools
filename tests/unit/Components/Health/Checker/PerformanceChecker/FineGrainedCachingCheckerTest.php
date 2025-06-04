<?php

namespace Frosh\Tools\Tests\unit\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\PerformanceChecker\FineGrainedCachingChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Test\SettingsResultAssertionHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FineGrainedCachingChecker::class)]
class FineGrainedCachingCheckerTest extends TestCase
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function fineGrainedCachingProvider(): array
    {
        return [
            'shopware version too low' => [
                'shopwareVersion' => '6.5.3.0',
                'cacheTaggingEachConfig' => true,
                'cacheTaggingEachSnippet' => true,
                'cacheTaggingEachThemeConfig' => true,
                'expectInfo' => false,
            ],
            'shopware version ok, all caching disabled' => [
                'shopwareVersion' => '6.5.4.0',
                'cacheTaggingEachConfig' => false,
                'cacheTaggingEachSnippet' => false,
                'cacheTaggingEachThemeConfig' => false,
                'expectInfo' => false,
            ],
            'shopware version ok, config caching enabled' => [
                'shopwareVersion' => '6.5.4.0',
                'cacheTaggingEachConfig' => true,
                'cacheTaggingEachSnippet' => false,
                'cacheTaggingEachThemeConfig' => false,
                'expectInfo' => true,
            ],
            'shopware version ok, snippet caching enabled' => [
                'shopwareVersion' => '6.5.4.0',
                'cacheTaggingEachConfig' => false,
                'cacheTaggingEachSnippet' => true,
                'cacheTaggingEachThemeConfig' => false,
                'expectInfo' => true,
            ],
            'shopware version ok, theme config caching enabled' => [
                'shopwareVersion' => '6.5.4.0',
                'cacheTaggingEachConfig' => false,
                'cacheTaggingEachSnippet' => false,
                'cacheTaggingEachThemeConfig' => true,
                'expectInfo' => true,
            ],
            'shopware version ok, all caching enabled' => [
                'shopwareVersion' => '6.5.5.0',
                'cacheTaggingEachConfig' => true,
                'cacheTaggingEachSnippet' => true,
                'cacheTaggingEachThemeConfig' => true,
                'expectInfo' => true,
            ],
        ];
    }

    #[DataProvider('fineGrainedCachingProvider')]
    public function testCollect(
        string $shopwareVersion,
        bool $cacheTaggingEachConfig,
        bool $cacheTaggingEachSnippet,
        bool $cacheTaggingEachThemeConfig,
        bool $expectInfo
    ): void {
        // Create the checker with our test configuration
        $checker = new FineGrainedCachingChecker(
            $shopwareVersion,
            $cacheTaggingEachConfig,
            $cacheTaggingEachSnippet,
            $cacheTaggingEachThemeConfig
        );

        // Create a health collection
        $collection = new HealthCollection();

        // Call the collect method
        $checker->collect($collection);

        if ($expectInfo) {
            // Assert that an info message was added to the collection
            SettingsResultAssertionHelper::assertSingleSettingsResult(
                $collection,
                'fine-grained-caching',
                SettingsResult::INFO,
                'enabled',
                'disabled',
                FineGrainedCachingChecker::DOCUMENTATION_URL
            );
        } else {
            // Assert that no info message was added to the collection
            SettingsResultAssertionHelper::assertSettingsResultCount($collection, 0);
        }
    }
}
