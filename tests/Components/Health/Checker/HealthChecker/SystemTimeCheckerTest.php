<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\HealthChecker\SystemTimeChecker;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Tests\Components\Health\Checker\AbstractCheckerTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;

class SystemTimeCheckerTest extends AbstractCheckerTestCase
{
    public function testCollectWithCurrentTime(): void
    {
        // This test checks that the checker can handle the actual system time
        // Since we can't easily mock the HTTP client without dependency injection,
        // we'll test the actual functionality
        $checker = new SystemTimeChecker();
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        // Should have one result
        $this->assertHealthCollectionCount($collection, 1);
        
        $result = $this->getResultById($collection, 'system-time');
        $this->assertNotNull($result);
        
        // The result should be either GREEN (synchronized) or WARNING (not synchronized/error)
        $this->assertContains($result->state, [SettingsResult::GREEN, SettingsResult::WARNING]);
    }

}