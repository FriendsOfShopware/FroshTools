<?php

namespace Frosh\Tools\Tests\unit\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\PerformanceChecker\AdminWorkerChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Test\SettingsResultAssertionHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdminWorkerChecker::class)]
class AdminWorkerCheckerTest extends TestCase
{
    /**
     * @return array<string, array<string, bool>>
     */
    public static function adminWorkerEnabledProvider(): array
    {
        return [
            'admin worker enabled' => [
                'adminWorkerEnabled' => true,
                'expectWarning' => true,
            ],
            'admin worker disabled' => [
                'adminWorkerEnabled' => false,
                'expectWarning' => false,
            ],
        ];
    }

    #[DataProvider('adminWorkerEnabledProvider')]
    public function testCollect(bool $adminWorkerEnabled, bool $expectWarning): void
    {
        // Create the checker with our test configuration
        $checker = new AdminWorkerChecker($adminWorkerEnabled);

        // Create a health collection
        $collection = new HealthCollection();

        // Call the collect method
        $checker->collect($collection);

        if ($expectWarning) {
            // Assert that a warning message was added to the collection
            SettingsResultAssertionHelper::assertSingleSettingsResult(
                $collection,
                'admin-watcher',
                SettingsResult::WARNING,
                'enabled',
                'disabled',
                'https://developer.shopware.com/docs/guides/plugins/plugins/framework/message-queue/add-message-handler#the-admin-worker'
            );
        } else {
            // Assert that no warning message was added to the collection
            SettingsResultAssertionHelper::assertSettingsResultCount($collection, 0);
        }
    }
}
