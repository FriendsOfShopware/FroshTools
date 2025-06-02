<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\HealthChecker\MysqlChecker;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Tests\Components\Health\Checker\AbstractCheckerTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class MysqlCheckerTest extends AbstractCheckerTestCase
{
    private Connection&MockObject $connection;
    private MysqlChecker $checker;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->checker = new MysqlChecker($this->connection);
    }

    public function testCollectWithMysql8Valid(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT VERSION()')
            ->willReturn('8.0.28');

        $collection = $this->createHealthCollection();
        $this->checker->collect($collection);

        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'mysql', SettingsResult::GREEN);

        $result = $this->getResultById($collection, 'mysql');
        $this->assertNotNull($result);
        $this->assertEquals('8.0.28', $result->current);
        $this->assertStringContainsString('min 8.0', $result->recommended);
    }

    public function testCollectWithMysql8BrokenVersion(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT VERSION()')
            ->willReturn('8.0.20');

        $collection = $this->createHealthCollection();
        $this->checker->collect($collection);

        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'mysql', SettingsResult::ERROR);

        $result = $this->getResultById($collection, 'mysql');
        $this->assertNotNull($result);
        $this->assertEquals('8.0.20', $result->current);
        $this->assertStringContainsString('not 8.0.20 or 8.0.21', $result->recommended);
    }

    public function testCollectWithMysqlOldVersion(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT VERSION()')
            ->willReturn('5.7.39');

        $collection = $this->createHealthCollection();
        $this->checker->collect($collection);

        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'mysql', SettingsResult::ERROR);

        $result = $this->getResultById($collection, 'mysql');
        $this->assertNotNull($result);
        $this->assertEquals('5.7.39', $result->current);
        $this->assertEquals('min 8.0', $result->recommended);
    }

    public function testCollectWithMariaDb1011(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT VERSION()')
            ->willReturn('10.11.2-MariaDB-1:10.11.2+maria~ubu2204');

        $collection = $this->createHealthCollection();
        $this->checker->collect($collection);

        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'mysql', SettingsResult::GREEN);

        $result = $this->getResultById($collection, 'mysql');
        $this->assertNotNull($result);
        $this->assertEquals('10.11.2', $result->current);
        $this->assertEquals('min 10.11', $result->recommended);
    }

    public function testCollectWithMariaDbOldVersion(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT VERSION()')
            ->willReturn('10.5.8-MariaDB');

        $collection = $this->createHealthCollection();
        $this->checker->collect($collection);

        // MariaDB versions below 10.11 don't add any result (no error check)
        $this->assertHealthCollectionCount($collection, 0);
    }

    public function testCollectWithUnknownVersion(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT VERSION()')
            ->willReturn(false);

        $collection = $this->createHealthCollection();
        $this->checker->collect($collection);

        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'mysql', SettingsResult::ERROR);

        $result = $this->getResultById($collection, 'mysql');
        $this->assertNotNull($result);
        $this->assertEquals('unknown', $result->current);
    }

    public function testCollectWithMysqlVersionWithSuffix(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT VERSION()')
            ->willReturn('8.0.32-0ubuntu0.22.04.2');

        $collection = $this->createHealthCollection();
        $this->checker->collect($collection);

        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'mysql', SettingsResult::GREEN);

        $result = $this->getResultById($collection, 'mysql');
        $this->assertNotNull($result);
        $this->assertEquals('8.0.32', $result->current);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('mariaDbVersionProvider')]
    public function testMariaDbVersionExtraction(string $versionString, string $expectedVersion): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT VERSION()')
            ->willReturn($versionString);

        $collection = $this->createHealthCollection();
        $this->checker->collect($collection);

        $result = $this->getResultById($collection, 'mysql');
        if ($result !== null) {
            $this->assertEquals($expectedVersion, $result->current);
        }
    }

    public static function mariaDbVersionProvider(): array
    {
        return [
            ['5.5.5-10.11.2-MariaDB-1:10.11.2+maria~ubu2204', '10.11.2'],
            ['mariadb-10.6.11', '10.6.11'],
            ['10.6.11-MariaDB', '10.6.11'],
        ];
    }
}