<?php
declare(strict_types=1);

namespace Frosh\Tools\Components;

use Shopware\Core\Framework\Adapter\Cache\CacheDecorator;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;

class CacheAdapter
{
    private readonly AdapterInterface $adapter;

    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $this->getCacheAdapter($adapter);
    }

    public function getSize(): int
    {
        switch (true) {
            case $this->adapter instanceof RedisAdapter:
            case $this->adapter instanceof RedisTagAwareAdapter:
                return $this->getRedis($this->adapter)->info()['used_memory'];
            case $this->adapter instanceof FilesystemAdapter:
                return CacheHelper::getSize($this->getPathFromFilesystemAdapter($this->adapter));
            case $this->adapter instanceof ArrayAdapter:
                return 0;
            case $this->adapter instanceof PhpFilesAdapter:
                return CacheHelper::getSize($this->getPathOfFilesAdapter($this->adapter));
            case $this->adapter instanceof ApcuAdapter:
                $aPCUIterator = new \APCUIterator();

                return $aPCUIterator->getTotalSize();
        }

        return -1;
    }

    public function getFreeSize(): ?int
    {
        switch (true) {
            case $this->adapter instanceof RedisAdapter:
            case $this->adapter instanceof RedisTagAwareAdapter:
                $info = $this->getRedis($this->adapter)->info();
                if ($info['maxmemory'] === 0) {
                    return -1;
                }

                return $info['maxmemory'] - $info['used_memory'];
            case $this->adapter instanceof FilesystemAdapter:
                return (int) disk_free_space($this->getPathFromFilesystemAdapter($this->adapter));
            case $this->adapter instanceof ArrayAdapter:
                return 0;
            case $this->adapter instanceof PhpFilesAdapter:
                return (int) disk_free_space($this->getPathOfFilesAdapter($this->adapter));
        }

        return -1;
    }

    public function clear(): void
    {
        switch (true) {
            case $this->adapter instanceof FilesystemAdapter:
                CacheHelper::removeDir($this->getPathFromFilesystemAdapter($this->adapter));

                break;
            case $this->adapter instanceof RedisAdapter:
            case $this->adapter instanceof RedisTagAwareAdapter:
                try {
                    $this->getRedis($this->adapter)->flushDB();
                } catch (\Exception) {
                    $this->adapter->clear();
                }

                break;
            case $this->adapter instanceof AdapterInterface:
                $this->adapter->clear();

                break;
        }
    }

    public function getType(): string
    {
        return match (true) {
            $this->adapter instanceof RedisAdapter, $this->adapter instanceof RedisTagAwareAdapter => 'Redis ' . $this->getRedis($this->adapter)->info()['redis_version'],
            $this->adapter instanceof FilesystemAdapter => 'Filesystem',
            $this->adapter instanceof ArrayAdapter => 'Array',
            $this->adapter instanceof PhpFilesAdapter => 'PHP files',
            $this->adapter instanceof ApcuAdapter => 'APCu',
            default => '',
        };
    }

    private function getCacheAdapter(AdapterInterface $adapter): AdapterInterface
    {
        if ($adapter instanceof CacheDecorator) {
            // Do not declare function as static
            $func = \Closure::bind(fn () => $adapter->decorated, $adapter, $adapter::class);

            return $this->getCacheAdapter($func());
        }

        if ($adapter instanceof TagAwareAdapter || $adapter instanceof TraceableAdapter) {
            // Do not declare function as static
            $func = \Closure::bind(fn () => $adapter->pool, $adapter, $adapter::class);

            return $this->getCacheAdapter($func());
        }

        return $adapter;
    }

    private function getRedis(AdapterInterface $adapter): ?\Redis
    {
        if ($adapter instanceof RedisTagAwareAdapter) {
            $redisProxyGetter = \Closure::bind(fn () => $adapter->redis, $adapter, RedisTagAwareAdapter::class);
        } else {
            $redisProxyGetter = \Closure::bind(fn () => $adapter->redis, $adapter, RedisAdapter::class);
        }

        return $redisProxyGetter();
    }

    private function getPathFromFilesystemAdapter(FilesystemAdapter $adapter): string
    {
        $getter = \Closure::bind(fn () => $adapter->directory, $adapter, $adapter::class);

        return $getter();
    }

    private function getPathOfFilesAdapter(PhpFilesAdapter $adapter): string
    {
        $getter = \Closure::bind(fn () => $adapter->directory, $adapter, $adapter::class);

        return $getter();
    }
}
