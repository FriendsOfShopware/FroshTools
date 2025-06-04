<?php

namespace Frosh\Tools\Tests\unit\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\PerformanceChecker\MessengerAutoSetupChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Test\SettingsResultAssertionHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessengerAutoSetupChecker::class)]
class MessengerAutoSetupCheckerTest extends TestCase
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function messengerDsnProvider(): array
    {
        return [
            'all DSNs have auto_setup enabled' => [
                'mainDsn' => 'doctrine://default?auto_setup=true',
                'lowPriorityDsn' => 'doctrine://default?auto_setup=true',
                'failureDsn' => 'doctrine://default?auto_setup=true',
                'expectInfo' => true,
            ],
            'only main DSN has auto_setup enabled' => [
                'mainDsn' => 'doctrine://default?auto_setup=true',
                'lowPriorityDsn' => 'doctrine://default?auto_setup=false',
                'failureDsn' => 'doctrine://default?auto_setup=false',
                'expectInfo' => true,
            ],
            'only low priority DSN has auto_setup enabled' => [
                'mainDsn' => 'doctrine://default?auto_setup=false',
                'lowPriorityDsn' => 'doctrine://default?auto_setup=true',
                'failureDsn' => 'doctrine://default?auto_setup=false',
                'expectInfo' => true,
            ],
            'only failure DSN has auto_setup enabled' => [
                'mainDsn' => 'doctrine://default?auto_setup=false',
                'lowPriorityDsn' => 'doctrine://default?auto_setup=false',
                'failureDsn' => 'doctrine://default?auto_setup=true',
                'expectInfo' => true,
            ],
            'all DSNs have auto_setup disabled' => [
                'mainDsn' => 'doctrine://default?auto_setup=false',
                'lowPriorityDsn' => 'doctrine://default?auto_setup=false',
                'failureDsn' => 'doctrine://default?auto_setup=false',
                'expectInfo' => false,
            ],
            'DSNs without auto_setup parameter (defaults to true)' => [
                'mainDsn' => 'doctrine://default',
                'lowPriorityDsn' => 'doctrine://default',
                'failureDsn' => 'doctrine://default',
                'expectInfo' => true,
            ],
            'mixed auto_setup values' => [
                'mainDsn' => 'doctrine://default?auto_setup=false',
                'lowPriorityDsn' => 'doctrine://default?auto_setup=true',
                'failureDsn' => 'doctrine://default?auto_setup=false',
                'expectInfo' => true,
            ],
            'empty DSNs' => [
                'mainDsn' => '',
                'lowPriorityDsn' => '',
                'failureDsn' => '',
                'expectInfo' => true,
            ],
        ];
    }

    #[DataProvider('messengerDsnProvider')]
    public function testCollect(string $mainDsn, string $lowPriorityDsn, string $failureDsn, bool $expectInfo): void
    {
        // Create the checker with our test DSNs
        $checker = new MessengerAutoSetupChecker($mainDsn, $lowPriorityDsn, $failureDsn);

        // Create a health collection
        $collection = new HealthCollection();

        // Call the collect method
        $checker->collect($collection);

        if ($expectInfo) {
            // Assert that an info message was added to the collection
            SettingsResultAssertionHelper::assertSingleSettingsResult(
                $collection,
                'messenger-auto-setup',
                SettingsResult::INFO,
                'enabled',
                'disabled',
                'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#disable-auto-setup'
            );
        } else {
            // Assert that no info message was added to the collection
            SettingsResultAssertionHelper::assertSettingsResultCount($collection, 0);
        }
    }
}
