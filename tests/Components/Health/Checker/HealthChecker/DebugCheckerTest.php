<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\HealthChecker\DebugChecker;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Tests\Components\Health\Checker\AbstractCheckerTestCase;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;

class DebugCheckerTest extends AbstractCheckerTestCase
{
    public function testCollectWithDebugModeOffAndNoWebProfiler(): void
    {
        $checker = new DebugChecker([], false);
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 2);
        $this->assertHealthCollectionContains($collection, 'webprofiler', SettingsResult::GREEN);
        $this->assertHealthCollectionContains($collection, 'kerneldebug', SettingsResult::GREEN);
    }

    public function testCollectWithDebugModeOn(): void
    {
        $checker = new DebugChecker([], true);
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 2);
        $this->assertHealthCollectionContains($collection, 'webprofiler', SettingsResult::GREEN);
        $this->assertHealthCollectionContains($collection, 'kerneldebug', SettingsResult::ERROR);
        
        $kernelDebugResult = $this->getResultById($collection, 'kerneldebug');
        $this->assertNotNull($kernelDebugResult);
        $this->assertEquals('active', $kernelDebugResult->current);
        $this->assertEquals('not active', $kernelDebugResult->recommended);
    }

    public function testCollectWithWebProfilerActive(): void
    {
        $checker = new DebugChecker([WebProfilerBundle::class => WebProfilerBundle::class], false);
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 2);
        $this->assertHealthCollectionContains($collection, 'webprofiler', SettingsResult::ERROR);
        $this->assertHealthCollectionContains($collection, 'kerneldebug', SettingsResult::GREEN);
        
        $webProfilerResult = $this->getResultById($collection, 'webprofiler');
        $this->assertNotNull($webProfilerResult);
        $this->assertEquals('active', $webProfilerResult->current);
        $this->assertEquals('not active', $webProfilerResult->recommended);
    }

    public function testCollectWithBothDebugAndWebProfilerActive(): void
    {
        $checker = new DebugChecker([WebProfilerBundle::class => WebProfilerBundle::class], true);
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 2);
        $this->assertHealthCollectionContains($collection, 'webprofiler', SettingsResult::ERROR);
        $this->assertHealthCollectionContains($collection, 'kerneldebug', SettingsResult::ERROR);
    }
}