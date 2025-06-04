<?php

namespace Frosh\Tools\Tests\unit\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\PerformanceChecker\IncrementStorageChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Test\SettingsResultAssertionHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(IncrementStorageChecker::class)]
class IncrementStorageCheckerTest extends TestCase
{
    /**
     * @return array<string, array<string, string|bool>>
     */
    public static function incrementStorageProvider(): array
    {
        return [
            'both using mysql' => [
                'userActivity' => 'mysql',
                'queueActivity' => 'mysql',
                'expectWarning' => true,
            ],
            'user activity using mysql' => [
                'userActivity' => 'mysql',
                'queueActivity' => 'redis',
                'expectWarning' => true,
            ],
            'queue activity using mysql' => [
                'userActivity' => 'array',
                'queueActivity' => 'mysql',
                'expectWarning' => true,
            ],
            'both using redis' => [
                'userActivity' => 'redis',
                'queueActivity' => 'redis',
                'expectWarning' => false,
            ],
            'both using array' => [
                'userActivity' => 'array',
                'queueActivity' => 'array',
                'expectWarning' => false,
            ],
            'mixed non-mysql storage' => [
                'userActivity' => 'array',
                'queueActivity' => 'redis',
                'expectWarning' => false,
            ],
        ];
    }

    #[DataProvider('incrementStorageProvider')]
    public function testCollect(string $userActivity, string $queueActivity, bool $expectWarning): void
    {
        // Create the checker with our test configuration
        $checker = new IncrementStorageChecker($userActivity, $queueActivity);

        // Create a health collection
        $collection = new HealthCollection();

        // Call the collect method
        $checker->collect($collection);

        if ($expectWarning) {
            // Assert that a warning message was added to the collection
            SettingsResultAssertionHelper::assertSingleSettingsResult(
                $collection,
                'increment-storage',
                SettingsResult::WARNING,
                'mysql',
                'array or redis',
                'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks#increment-storage'
            );
        } else {
            // Assert that no warning message was added to the collection
            SettingsResultAssertionHelper::assertSettingsResultCount($collection, 0);
        }
    }
}
