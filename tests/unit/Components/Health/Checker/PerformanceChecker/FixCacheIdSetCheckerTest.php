<?php

namespace Frosh\Tools\Tests\unit\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\PerformanceChecker\FixCacheIdSetChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Test\SettingsResultAssertionHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FixCacheIdSetChecker::class)]
class FixCacheIdSetCheckerTest extends TestCase
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function cacheIdProvider(): array
    {
        return [
            'shopware version too low' => [
                'shopwareVersion' => '6.4.10.0',
                'cacheId' => null,
                'expectWarning' => false,
            ],
            'shopware version ok, cache id not set' => [
                'shopwareVersion' => '6.4.11.0',
                'cacheId' => null,
                'expectWarning' => true,
            ],
            'shopware version ok, cache id empty' => [
                'shopwareVersion' => '6.4.11.0',
                'cacheId' => '',
                'expectWarning' => true,
            ],
            'shopware version ok, cache id set' => [
                'shopwareVersion' => '6.4.11.0',
                'cacheId' => 'my-cache-id',
                'expectWarning' => false,
            ],
            'shopware version newer, cache id not set' => [
                'shopwareVersion' => '6.5.0.0',
                'cacheId' => null,
                'expectWarning' => true,
            ],
            'shopware version newer, cache id set' => [
                'shopwareVersion' => '6.5.0.0',
                'cacheId' => 'my-cache-id',
                'expectWarning' => false,
            ],
        ];
    }

    #[DataProvider('cacheIdProvider')]
    public function testCollect(string $shopwareVersion, ?string $cacheId, bool $expectWarning): void
    {
        // Create the checker with our test configuration
        $checker = new FixCacheIdSetChecker($shopwareVersion, $cacheId);

        // Create a health collection
        $collection = new HealthCollection();

        // Call the collect method
        $checker->collect($collection);

        if ($expectWarning) {
            // Assert that a warning message was added to the collection
            SettingsResultAssertionHelper::assertSingleSettingsResult(
                $collection,
                'cache-id',
                SettingsResult::WARNING,
                'not set',
                'set',
                'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks#cache-id'
            );
        } else {
            // Assert that no warning message was added to the collection
            SettingsResultAssertionHelper::assertSettingsResultCount($collection, 0);
        }
    }
}
