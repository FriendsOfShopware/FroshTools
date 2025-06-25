<?php

namespace Frosh\Tools\Tests\unit\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\PerformanceChecker\DisableAppUrlExternalCheckChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Test\SettingsResultAssertionHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DisableAppUrlExternalCheckChecker::class)]
class DisableAppUrlExternalCheckCheckerTest extends TestCase
{
    /**
     * @return array<string, array<string, bool>>
     */
    public static function appUrlCheckDisabledProvider(): array
    {
        return [
            'app url check enabled (default)' => [
                'appUrlCheckDisabled' => false,
                'expectWarning' => true,
            ],
            'app url check disabled' => [
                'appUrlCheckDisabled' => true,
                'expectWarning' => false,
            ],
        ];
    }

    #[DataProvider('appUrlCheckDisabledProvider')]
    public function testCollect(bool $appUrlCheckDisabled, bool $expectWarning): void
    {
        // Create the checker with our test configuration
        $checker = new DisableAppUrlExternalCheckChecker($appUrlCheckDisabled);

        // Create a health collection
        $collection = new HealthCollection();

        // Call the collect method
        $checker->collect($collection);

        if ($expectWarning) {
            // Assert that a warning message was added to the collection
            SettingsResultAssertionHelper::assertSingleSettingsResult(
                $collection,
                'app-url-check-disabled',
                SettingsResult::WARNING,
                'enabled',
                'disabled',
                'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#disable-app-url-external-check'
            );
        } else {
            // Assert that no warning message was added to the collection
            SettingsResultAssertionHelper::assertSettingsResultCount($collection, 0);
        }
    }
}
