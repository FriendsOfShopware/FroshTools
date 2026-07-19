<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components;

use Frosh\Tools\Components\DatabaseStatisticsService;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DatabaseStatisticsService::class)]
class DatabaseStatisticsServiceTest extends IntegrationTestCase
{
    private DatabaseStatisticsService $service;

    protected function setUp(): void
    {
        $this->service = static::getContainer()->get(DatabaseStatisticsService::class);
    }

    public function testGetServerInfo(): void
    {
        $info = $this->service->getServerInfo();

        static::assertArrayHasKey('version', $info);
        static::assertNotSame('', $info['version']);
        static::assertGreaterThanOrEqual(0, $info['uptime']);
        static::assertGreaterThanOrEqual(0, $info['threads']);
        static::assertGreaterThanOrEqual(0, $info['questions']);
        static::assertGreaterThanOrEqual(0, $info['slowQueries']);
        static::assertIsFloat($info['queriesPerSecond']);
    }

    public function testGetTableStatistics(): void
    {
        $tables = $this->service->getTableStatistics();

        static::assertNotEmpty($tables);

        $names = array_column($tables, 'name');
        static::assertContains('product', $names);

        foreach ($tables as $table) {
            static::assertArrayHasKey('name', $table);
            static::assertArrayHasKey('engine', $table);
            static::assertArrayHasKey('rows', $table);
            static::assertArrayHasKey('dataSize', $table);
            static::assertArrayHasKey('indexSize', $table);
            static::assertArrayHasKey('totalSize', $table);
            static::assertSame($table['dataSize'] + $table['indexSize'], $table['totalSize']);
        }

        $sizes = array_column($tables, 'totalSize');
        $sorted = $sizes;
        rsort($sorted);
        static::assertSame($sorted, $sizes, 'Tables should be sorted by total size descending');
    }

    public function testGetGlobalStatus(): void
    {
        $status = $this->service->getGlobalStatus();

        foreach (['bufferPoolSize', 'bufferPoolUsed', 'bufferPoolHitRate', 'threadsConnected', 'threadsRunning', 'slowQueries', 'tmpDiskTables', 'tmpTables'] as $key) {
            static::assertArrayHasKey($key, $status);
        }

        static::assertGreaterThan(0, $status['bufferPoolSize']);
        static::assertGreaterThanOrEqual(0, $status['bufferPoolHitRate']);
        static::assertLessThanOrEqual(100, $status['bufferPoolHitRate']);
    }
}
