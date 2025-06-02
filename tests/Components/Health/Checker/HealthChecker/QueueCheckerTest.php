<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Frosh\Tools\Components\Health\Checker\HealthChecker\QueueChecker;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Tests\Components\Health\Checker\AbstractCheckerTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class QueueCheckerTest extends AbstractCheckerTestCase
{
    private Connection&MockObject $connection;
    private SystemConfigService&MockObject $systemConfigService;
    private QueueChecker $checker;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->checker = new QueueChecker($this->connection, $this->systemConfigService);
    }

    public function testCollectWithNoMessages(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('SELECT available_at FROM messenger_messages'))
            ->willReturn(false);

        $this->systemConfigService->expects($this->once())
            ->method('getInt')
            ->with('FroshTools.config.monitorQueueGraceTime')
            ->willReturn(15);

        $collection = $this->createHealthCollection();
        $this->checker->collect($collection);

        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'queue', SettingsResult::INFO);

        $queueResult = $this->getResultById($collection, 'queue');
        $this->assertNotNull($queueResult);
        $this->assertEquals('unknown', $queueResult->current);
    }

    public function testCollectWithRecentMessages(): void
    {
        // Message available 3 minutes ago (within 15 minute grace period)
        $availableAt = (new \DateTime('-3 minutes'))->format('Y-m-d H:i:s');
        
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('SELECT available_at FROM messenger_messages'))
            ->willReturn($availableAt);

        $this->systemConfigService->expects($this->once())
            ->method('getInt')
            ->with('FroshTools.config.monitorQueueGraceTime')
            ->willReturn(15);

        $collection = $this->createHealthCollection();
        $this->checker->collect($collection);

        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'queue', SettingsResult::GREEN);

        $queueResult = $this->getResultById($collection, 'queue');
        $this->assertNotNull($queueResult);
        $this->assertStringContainsString('mins', $queueResult->current);
    }

    public function testCollectWithOldMessages(): void
    {
        // Message available 30 minutes ago (older than 15 minute grace period)
        // The diff calculation is: abs((message_time - grace_limit_time) / 60)
        // Grace limit is 15 minutes ago, message is 30 minutes ago
        // So diff = abs((-30*60) - (-15*60)) / 60 = abs(-1800 + 900) / 60 = 900/60 = 15
        // Since diff (15) == maxDiff (15), it should be OK, not WARNING
        // Let's use 31 minutes to make diff > maxDiff
        $availableAt = (new \DateTime('-31 minutes'))->format('Y-m-d H:i:s');
        
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('SELECT available_at FROM messenger_messages'))
            ->willReturn($availableAt);

        $this->systemConfigService->expects($this->once())
            ->method('getInt')
            ->with('FroshTools.config.monitorQueueGraceTime')
            ->willReturn(15);

        $collection = $this->createHealthCollection();
        $this->checker->collect($collection);

        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'queue', SettingsResult::WARNING);

        $queueResult = $this->getResultById($collection, 'queue');
        $this->assertNotNull($queueResult);
        $this->assertStringContainsString('mins', $queueResult->current);
    }

    public function testCollectWithCustomGraceTime(): void
    {
        // Message available 18 minutes ago
        $availableAt = (new \DateTime('-18 minutes'))->format('Y-m-d H:i:s');
        
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('SELECT available_at FROM messenger_messages'))
            ->willReturn($availableAt);

        // Custom grace time of 30 minutes
        $this->systemConfigService->expects($this->once())
            ->method('getInt')
            ->with('FroshTools.config.monitorQueueGraceTime')
            ->willReturn(30);

        $collection = $this->createHealthCollection();
        $this->checker->collect($collection);

        // Should be OK because 18 minutes < 30 minutes grace time
        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'queue', SettingsResult::GREEN);
    }

    public function testCollectWithInvalidDateFormat(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with($this->stringContains('SELECT available_at FROM messenger_messages'))
            ->willReturn('invalid-date');

        $this->systemConfigService->expects($this->once())
            ->method('getInt')
            ->with('FroshTools.config.monitorQueueGraceTime')
            ->willReturn(15);

        $collection = $this->createHealthCollection();
        
        // The DateTime constructor should throw an exception for invalid date
        $this->expectException(\Exception::class);
        $this->checker->collect($collection);
    }
}