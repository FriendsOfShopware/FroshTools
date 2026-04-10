<?php

declare(strict_types=1);

namespace Frosh\Tools\Components;

use Doctrine\DBAL\Connection;

class DatabaseStatisticsService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array{version: string, uptime: int, threads: int, questions: int, slowQueries: int, queriesPerSecond: float}
     */
    public function getServerInfo(): array
    {
        $version = $this->connection->fetchOne('SELECT VERSION()');

        $statusVars = $this->fetchGlobalStatusMap([
            'Uptime',
            'Threads_connected',
            'Questions',
            'Slow_queries',
            'Queries',
        ]);

        $uptime = (int) ($statusVars['Uptime'] ?? 0);
        $questions = (int) ($statusVars['Questions'] ?? 0);

        return [
            'version' => \is_string($version) ? $version : 'unknown',
            'uptime' => $uptime,
            'threads' => (int) ($statusVars['Threads_connected'] ?? 0),
            'questions' => $questions,
            'slowQueries' => (int) ($statusVars['Slow_queries'] ?? 0),
            'queriesPerSecond' => $uptime > 0 ? round($questions / $uptime, 2) : 0.0,
        ];
    }

    /**
     * @return list<array{name: string, engine: string|null, rows: int, dataSize: int, indexSize: int, totalSize: int}>
     */
    public function getTableStatistics(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT TABLE_NAME, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC'
        );

        $result = [];

        foreach ($rows as $row) {
            $dataSize = (int) ($row['DATA_LENGTH'] ?? 0);
            $indexSize = (int) ($row['INDEX_LENGTH'] ?? 0);

            $result[] = [
                'name' => (string) ($row['TABLE_NAME'] ?? ''),
                'engine' => $row['ENGINE'] !== null ? (string) $row['ENGINE'] : null,
                'rows' => (int) ($row['TABLE_ROWS'] ?? 0),
                'dataSize' => $dataSize,
                'indexSize' => $indexSize,
                'totalSize' => $dataSize + $indexSize,
            ];
        }

        return $result;
    }

    /**
     * @return array{bufferPoolSize: int, bufferPoolUsed: int, bufferPoolHitRate: float, threadsConnected: int, threadsRunning: int, slowQueries: int, tmpDiskTables: int, tmpTables: int}
     */
    public function getGlobalStatus(): array
    {
        $statusVars = $this->fetchGlobalStatusMap([
            'Innodb_buffer_pool_read_requests',
            'Innodb_buffer_pool_reads',
            'Innodb_buffer_pool_pages_total',
            'Innodb_buffer_pool_pages_free',
            'Threads_connected',
            'Threads_running',
            'Slow_queries',
            'Created_tmp_disk_tables',
            'Created_tmp_tables',
        ]);

        $variables = $this->fetchGlobalVariableMap([
            'innodb_buffer_pool_size',
        ]);

        $bufferPoolSize = (int) ($variables['innodb_buffer_pool_size'] ?? 0);
        $pagesTotal = (int) ($statusVars['Innodb_buffer_pool_pages_total'] ?? 0);
        $pagesFree = (int) ($statusVars['Innodb_buffer_pool_pages_free'] ?? 0);
        $bufferPoolUsed = $pagesTotal > 0 && $bufferPoolSize > 0
            ? (int) (($pagesTotal - $pagesFree) / $pagesTotal * $bufferPoolSize)
            : 0;

        $readRequests = (int) ($statusVars['Innodb_buffer_pool_read_requests'] ?? 0);
        $diskReads = (int) ($statusVars['Innodb_buffer_pool_reads'] ?? 0);
        $hitRate = $readRequests > 0
            ? round(($readRequests - $diskReads) / $readRequests * 100, 2)
            : 0.0;

        return [
            'bufferPoolSize' => $bufferPoolSize,
            'bufferPoolUsed' => $bufferPoolUsed,
            'bufferPoolHitRate' => $hitRate,
            'threadsConnected' => (int) ($statusVars['Threads_connected'] ?? 0),
            'threadsRunning' => (int) ($statusVars['Threads_running'] ?? 0),
            'slowQueries' => (int) ($statusVars['Slow_queries'] ?? 0),
            'tmpDiskTables' => (int) ($statusVars['Created_tmp_disk_tables'] ?? 0),
            'tmpTables' => (int) ($statusVars['Created_tmp_tables'] ?? 0),
        ];
    }

    /**
     * @param list<string> $names
     * @return array<string, string>
     */
    private function fetchGlobalStatusMap(array $names): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SHOW GLOBAL STATUS WHERE Variable_name IN (?)',
            [$names],
            [\Doctrine\DBAL\ArrayParameterType::STRING]
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['Variable_name']] = (string) $row['Value'];
        }

        return $map;
    }

    /**
     * @param list<string> $names
     * @return array<string, string>
     */
    private function fetchGlobalVariableMap(array $names): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SHOW GLOBAL VARIABLES WHERE Variable_name IN (?)',
            [$names],
            [\Doctrine\DBAL\ArrayParameterType::STRING]
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['Variable_name']] = (string) $row['Value'];
        }

        return $map;
    }
}
