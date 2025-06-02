<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\HealthChecker\SystemInfoChecker;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Tests\Components\Health\Checker\AbstractCheckerTestCase;

class SystemInfoCheckerTest extends AbstractCheckerTestCase
{
    public function testCollect(): void
    {
        $projectDir = '/var/www/shopware';
        
        $checker = new SystemInfoChecker($projectDir);
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 2);
        
        // Check installation path
        $pathResult = $this->getResultById($collection, 'installation-path');
        $this->assertNotNull($pathResult);
        $this->assertEquals(SettingsResult::GREEN, $pathResult->state);
        $this->assertEquals($projectDir, $pathResult->current);
        
        // Check database info - it will use environment variables
        $dbResult = $this->getResultById($collection, 'database-info');
        $this->assertNotNull($dbResult);
        $this->assertEquals(SettingsResult::GREEN, $dbResult->state);
        // We can't predict the exact database info as it comes from env
        $this->assertNotEmpty($dbResult->current);
    }

    public function testCollectWithDifferentProjectDir(): void
    {
        $projectDir = '/home/shopware/production';
        
        $checker = new SystemInfoChecker($projectDir);
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 2);
        
        // Check installation path
        $pathResult = $this->getResultById($collection, 'installation-path');
        $this->assertNotNull($pathResult);
        $this->assertEquals($projectDir, $pathResult->current);
    }

    public function testDatabaseInfoFormat(): void
    {
        $projectDir = '/opt/shopware';
        
        $checker = new SystemInfoChecker($projectDir);
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 2);
        
        // Check database info format
        $dbResult = $this->getResultById($collection, 'database-info');
        $this->assertNotNull($dbResult);
        // The format should be username@host:port/database
        $this->assertMatchesRegularExpression('/.*@.*:.*\/.*/', $dbResult->current);
    }
}