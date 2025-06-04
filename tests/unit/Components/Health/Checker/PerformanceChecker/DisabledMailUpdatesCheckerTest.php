<?php

namespace Frosh\Tools\Tests\unit\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\PerformanceChecker\DisabledMailUpdatesChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Test\SettingsResultAssertionHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[CoversClass(DisabledMailUpdatesChecker::class)]
class DisabledMailUpdatesCheckerTest extends TestCase
{
    public function testCollectAddsWarningWhenMailVariableUpdatesAreEnabled(): void
    {
        $parameterBag = new ParameterBag([
            'shopware.mail.update_mail_variables_on_send' => true
        ]);

        // Create the checker with our mock
        $checker = new DisabledMailUpdatesChecker($parameterBag);

        // Create a health collection
        $collection = new HealthCollection();

        // Call the collect method
        $checker->collect($collection);

        // Assert that a warning was added to the collection
        SettingsResultAssertionHelper::assertSingleSettingsResult(
            $collection,
            'mail_variables',
            SettingsResult::WARNING,
            'enabled',
            'disabled',
            'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks#prevent-mail-data-updates'
        );
    }

    public function testCollectDoesNotAddWarningWhenMailVariableUpdatesAreDisabled(): void
    {
        $parameterBag = new ParameterBag([
            'shopware.mail.update_mail_variables_on_send' => false
        ]);

        // Create the checker with our mock
        $checker = new DisabledMailUpdatesChecker($parameterBag);

        // Create a health collection
        $collection = new HealthCollection();

        // Call the collect method
        $checker->collect($collection);

        // Assert that no warning was added to the collection
        SettingsResultAssertionHelper::assertSettingsResultCount($collection, 0);
    }

    public function testCollectDoesNotAddWarningWhenParameterDoesNotExist(): void
    {
        $parameterBag = new ParameterBag([]);

        // Create the checker with our mock
        $checker = new DisabledMailUpdatesChecker($parameterBag);

        // Create a health collection
        $collection = new HealthCollection();

        // Call the collect method
        $checker->collect($collection);

        // Assert that no warning was added to the collection
        SettingsResultAssertionHelper::assertSettingsResultCount($collection, 0);
    }
}
