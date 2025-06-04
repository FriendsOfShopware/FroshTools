<?php

namespace Frosh\Tools\Tests\unit\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\PerformanceChecker\MailOverQueueChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Test\SettingsResultAssertionHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MailOverQueueChecker::class)]
class MailOverQueueCheckerTest extends TestCase
{
    public function testCollectAddsWarningWhenMailerIsNotOverQueue(): void
    {
        // Create a MailOverQueueChecker with mailerIsOverQueue = false
        $checker = new MailOverQueueChecker(false);
        
        // Create a new HealthCollection to collect results
        $collection = new HealthCollection();
        
        // Call the collect method
        $checker->collect($collection);
        
        // Assert that a warning was added to the collection
        SettingsResultAssertionHelper::assertSingleSettingsResult(
            $collection,
            'mail',
            SettingsResult::WARNING,
            'disabled',
            'enabled',
            'https://developer.shopware.com/docs/guides/hosting/infrastructure/message-queue#sending-mails-over-the-message-queue'
        );
    }
    
    public function testCollectDoesNotAddWarningWhenMailerIsOverQueue(): void
    {
        // Create a MailOverQueueChecker with mailerIsOverQueue = true
        $checker = new MailOverQueueChecker(true);
        
        // Create a new HealthCollection to collect results
        $collection = new HealthCollection();
        
        // Call the collect method
        $checker->collect($collection);
        
        // Assert that no warning was added to the collection
        SettingsResultAssertionHelper::assertSettingsResultCount($collection, 0);
    }
}
