<?php

declare(strict_types=1);

namespace Frosh\Tools\Components;

class CacheStatisticsService
{
    public function __construct(private readonly CacheRegistry $cacheRegistry)
    {
    }

    /**
     * @return array{enabled: bool, hitRate: float, hits: int, misses: int, usedMemory: int, freeMemory: int, wastedMemory: int, wastedPercentage: float, cachedScripts: int, maxCachedScripts: int, internedStringsUsedMemory: int, internedStringsFreeMemory: int, lastRestart: string|null}|null
     */
    public function getOpcacheStatistics(): ?array
    {
        if (!\function_exists('opcache_get_status')) {
            return null;
        }

        $status = opcache_get_status(false);

        if ($status === false) {
            return null;
        }

        $config = \function_exists('opcache_get_configuration') ? opcache_get_configuration() : false;

        $memory = $status['memory_usage'] ?? [];
        $stats = $status['opcache_statistics'] ?? [];
        $interned = $status['interned_strings_usage'] ?? [];

        $hits = (int) ($stats['hits'] ?? 0);
        $misses = (int) ($stats['misses'] ?? 0);
        $total = $hits + $misses;

        $maxScripts = 0;
        if ($config !== false) {
            $maxScripts = (int) ($config['directives']['opcache.max_accelerated_files'] ?? 0);
        }

        $lastRestart = null;
        if (isset($stats['last_restart_time']) && $stats['last_restart_time'] > 0) {
            $lastRestart = (new \DateTimeImmutable('@' . $stats['last_restart_time']))->format('c');
        }

        return [
            'enabled' => (bool) ($status['opcache_enabled'] ?? false),
            'hitRate' => $total > 0 ? round($hits / $total * 100, 2) : 0.0,
            'hits' => $hits,
            'misses' => $misses,
            'usedMemory' => (int) ($memory['used_memory'] ?? 0),
            'freeMemory' => (int) ($memory['free_memory'] ?? 0),
            'wastedMemory' => (int) ($memory['wasted_memory'] ?? 0),
            'wastedPercentage' => round((float) ($memory['current_wasted_percentage'] ?? 0), 2),
            'cachedScripts' => (int) ($stats['num_cached_scripts'] ?? 0),
            'maxCachedScripts' => $maxScripts,
            'internedStringsUsedMemory' => (int) ($interned['used_memory'] ?? 0),
            'internedStringsFreeMemory' => (int) ($interned['free_memory'] ?? 0),
            'lastRestart' => $lastRestart,
        ];
    }

    /**
     * @return array{enabled: bool, hitRate: float, hits: int, misses: int, usedMemory: int, availableMemory: int, entries: int, fragmentation: float}|null
     */
    public function getApcuStatistics(): ?array
    {
        if (!\function_exists('apcu_cache_info') || !\function_exists('apcu_sma_info')) {
            return null;
        }

        $cacheInfo = apcu_cache_info(true);
        $smaInfo = apcu_sma_info();

        if ($cacheInfo === false || $smaInfo === false) {
            return null;
        }

        $hits = (int) ($cacheInfo['num_hits'] ?? 0);
        $misses = (int) ($cacheInfo['num_misses'] ?? 0);
        $total = $hits + $misses;

        $availableMemory = (int) ($smaInfo['avail_mem'] ?? 0);
        $totalSegSize = (int) ($smaInfo['num_seg'] ?? 1) * (int) ($smaInfo['seg_size'] ?? 0);
        $usedMemory = $totalSegSize - $availableMemory;

        $fragmentation = 0.0;
        if ($totalSegSize > 0) {
            $fragmentation = round(($smaInfo['num_seg'] > 0 ? (1 - $availableMemory / $totalSegSize) : 0) * 100, 2);
        }

        return [
            'enabled' => true,
            'hitRate' => $total > 0 ? round($hits / $total * 100, 2) : 0.0,
            'hits' => $hits,
            'misses' => $misses,
            'usedMemory' => max(0, $usedMemory),
            'availableMemory' => $availableMemory,
            'entries' => (int) ($cacheInfo['num_entries'] ?? 0),
            'fragmentation' => $fragmentation,
        ];
    }

    /**
     * @return list<array{name: string, version: string, uptime: int, hits: int, misses: int, hitRate: float, usedMemory: int, peakMemory: int, maxMemory: int, evictedKeys: int, expiredKeys: int, totalKeys: int, connectedClients: int, opsPerSec: int}>
     */
    public function getRedisStatistics(): array
    {
        $result = [];
        $seenConnections = [];

        foreach ($this->cacheRegistry->all() as $name => $adapter) {
            try {
                $redis = $adapter->getRedisOrFail();
            } catch (\RuntimeException) {
                continue;
            }

            $connectionId = $redis->getHost() . ':' . $redis->getPort();
            if (\in_array($connectionId, $seenConnections, true)) {
                continue;
            }
            $seenConnections[] = $connectionId;

            try {
                $info = $redis->info();
            } catch (\RedisException) {
                continue;
            }

            $hits = (int) ($info['keyspace_hits'] ?? 0);
            $misses = (int) ($info['keyspace_misses'] ?? 0);
            $total = $hits + $misses;

            $totalKeys = 0;
            foreach ($info as $key => $value) {
                if (\str_starts_with($key, 'db') && \is_string($value) && preg_match('/keys=(\d+)/', $value, $matches)) {
                    $totalKeys += (int) $matches[1];
                }
            }

            $result[] = [
                'name' => $name,
                'version' => (string) ($info['redis_version'] ?? 'unknown'),
                'uptime' => (int) ($info['uptime_in_seconds'] ?? 0),
                'hits' => $hits,
                'misses' => $misses,
                'hitRate' => $total > 0 ? round($hits / $total * 100, 2) : 0.0,
                'usedMemory' => (int) ($info['used_memory'] ?? 0),
                'peakMemory' => (int) ($info['used_memory_peak'] ?? 0),
                'maxMemory' => (int) ($info['maxmemory'] ?? 0),
                'evictedKeys' => (int) ($info['evicted_keys'] ?? 0),
                'expiredKeys' => (int) ($info['expired_keys'] ?? 0),
                'totalKeys' => $totalKeys,
                'connectedClients' => (int) ($info['connected_clients'] ?? 0),
                'opsPerSec' => (int) ($info['instantaneous_ops_per_sec'] ?? 0),
            ];
        }

        return $result;
    }
}
