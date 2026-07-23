<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\CacheAdapter;
use Frosh\Tools\Components\CacheRegistry;
use Frosh\Tools\Components\Health\Checker\PerformanceChecker\RedisTagAwareChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RedisTagAwareChecker::class)]
class RedisTagAwareCheckerTest extends TestCase
{
    public function testDoesNothingWhenHttpPoolMissing(): void
    {
        $registry = $this->createMock(CacheRegistry::class);
        $registry->method('has')->with('cache.http')->willReturn(false);
        $registry->expects(static::never())->method('get');

        $collection = new HealthCollection();
        (new RedisTagAwareChecker($registry))->collect($collection);

        static::assertCount(0, $collection);
    }

    public function testDoesNothingWhenHttpUsesTagAwareRedis(): void
    {
        $collection = $this->collectWithHttpType(CacheAdapter::TYPE_REDIS_TAG_AWARE . ' 7.2.0');

        static::assertCount(0, $collection);
    }

    public function testDoesNothingWhenHttpIsNotRedis(): void
    {
        $collection = $this->collectWithHttpType(CacheAdapter::TYPE_FILESYSTEM);

        static::assertCount(0, $collection);
    }

    public function testDoesNothingWhenObjectWouldBePlainRedisButHttpIsTagAware(): void
    {
        // Regression for #245: plain Redis on cache.object must not trigger this check.
        // The checker only reads cache.http — TagAware HTTP + plain object is valid.
        $http = $this->createMock(CacheAdapter::class);
        $http->method('getType')->willReturn(CacheAdapter::TYPE_REDIS_TAG_AWARE . ' 7.2.0');

        $registry = $this->createMock(CacheRegistry::class);
        $registry->method('has')->with('cache.http')->willReturn(true);
        $registry->method('get')->with('cache.http')->willReturn($http);

        $collection = new HealthCollection();
        (new RedisTagAwareChecker($registry))->collect($collection);

        static::assertCount(0, $collection);
    }

    public function testWarnsWhenHttpUsesPlainRedis(): void
    {
        $collection = $this->collectWithHttpType(CacheAdapter::TYPE_REDIS . ' 7.2.0');

        static::assertCount(1, $collection);
        /** @var SettingsResult $result */
        $result = $collection->first();
        static::assertSame(SettingsResult::WARNING, $result->state);
        static::assertSame('redis-tag-aware', $result->id);
        static::assertStringContainsString('cache.http', $result->getVars()['snippet']);
        static::assertStringContainsString('HTTP cache', $result->getVars()['snippet']);
        static::assertStringStartsWith(CacheAdapter::TYPE_REDIS, $result->current);
        static::assertSame(CacheAdapter::TYPE_REDIS_TAG_AWARE, $result->recommended);
    }

    private function collectWithHttpType(string $type): HealthCollection
    {
        $http = $this->createMock(CacheAdapter::class);
        $http->method('getType')->willReturn($type);

        $registry = $this->createMock(CacheRegistry::class);
        $registry->method('has')->with('cache.http')->willReturn(true);
        $registry->method('get')->with('cache.http')->willReturn($http);

        $collection = new HealthCollection();
        (new RedisTagAwareChecker($registry))->collect($collection);

        return $collection;
    }
}
