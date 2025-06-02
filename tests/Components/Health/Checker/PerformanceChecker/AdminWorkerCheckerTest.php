<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\PerformanceChecker\AdminWorkerChecker;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Tests\Components\Health\Checker\AbstractCheckerTestCase;

class AdminWorkerCheckerTest extends AbstractCheckerTestCase
{
    public function testCollectWithAdminWorkerEnabled(): void
    {
        $checker = new AdminWorkerChecker(true);
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'admin-watcher', SettingsResult::WARNING);
        
        $result = $this->getResultById($collection, 'admin-watcher');
        $this->assertNotNull($result);
        $this->assertEquals('Admin-Worker', $this->getProtectedProperty($result, 'snippet'));
        $this->assertEquals('enabled', $result->current);
        $this->assertEquals('disabled', $result->recommended);
        $this->assertEquals('https://developer.shopware.com/docs/guides/plugins/plugins/framework/message-queue/add-message-handler#the-admin-worker', $result->url);
    }

    public function testCollectWithAdminWorkerDisabled(): void
    {
        $checker = new AdminWorkerChecker(false);
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 0);
    }
}