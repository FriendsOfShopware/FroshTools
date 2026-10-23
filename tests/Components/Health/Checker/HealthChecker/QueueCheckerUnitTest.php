<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\HealthChecker\QueueChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Components\Queue\QueueAdapter;
use Frosh\Tools\Components\Queue\QueueRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(QueueChecker::class)]
class QueueCheckerUnitTest extends TestCase
{
    /**
     * @param array<string, array{?int, ?int}> $queues Count and oldest age in seconds, by transport name
     * @param array<string, mixed>             $config
     */
    #[DataProvider('queueCases')]
    public function testConfiguredTransports(array $queues, array $config, string $state, string $current, string $recommended = 'max 15 mins'): void
    {
        $adapters = [];
        foreach ($queues as $name => [$count, $age]) {
            $adapter = $this->createMock(QueueAdapter::class);
            $excluded = (($config['monitorExcludeFailedQueues'] ?? true) && str_contains(strtolower($name), 'failed'))
                || (isset($config['monitorQueues']) && !\in_array($name, array_map(trim(...), explode(',', $config['monitorQueues'])), true));
            $adapter->expects($excluded ? static::never() : static::once())->method('getMessageCount')->willReturn($count);
            $adapter->expects($excluded || 0 === $count ? static::never() : static::once())->method('getOldestMessageAge')->willReturn($age);
            // Health monitoring must never consume, browse or requeue messages (especially AMQP).
            $adapter->expects(static::never())->method('getMessages');
            $adapters[$name] = $adapter;
        }

        $registry = $this->createMock(QueueRegistry::class);
        $registry->expects(static::once())->method('all')->willReturn($adapters);
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('getInt')->willReturnCallback(static fn (string $key): int => (int) ($config[substr($key, 18)] ?? 0));
        $configService->method('getString')->willReturnCallback(static fn (string $key): string => (string) ($config[substr($key, 18)] ?? ''));
        $configService->method('get')->willReturnCallback(static fn (string $key): mixed => $config[substr($key, 18)] ?? null);

        $collection = new HealthCollection();
        (new QueueChecker($registry, $configService))->collect($collection);
        $results = array_values($collection->getElements());
        static::assertCount(1, $results);
        static::assertSame('queue', $results[0]->id);
        static::assertSame($state, $results[0]->state);
        static::assertSame($current, $results[0]->current);
        static::assertSame($recommended, $results[0]->recommended);
    }

    /**
     * @return iterable<string, array{array<string, array{?int, ?int}>, array<string, mixed>, string, string, string?: string}>
     */
    public static function queueCases(): iterable
    {
        yield 'no transports' => [[], [], SettingsResult::INFO, '0 mins'];
        yield 'empty RabbitMQ ignores any stale age' => [['async' => [0, 128014 * 60]], [], SettingsResult::INFO, '0 mins'];
        yield 'RabbitMQ cannot measure age' => [['async' => [5, null]], [], SettingsResult::INFO, 'Age unavailable (async)'];
        yield 'unknown transport count and age' => [['custom' => [null, null]], [], SettingsResult::INFO, 'Age unavailable (custom)'];
        yield 'age without count' => [['async' => [null, 1200]], [], SettingsResult::WARNING, '20 mins (async)'];
        yield 'old pending Redis or Doctrine message' => [['async' => [1, 7200]], [], SettingsResult::WARNING, '120 mins (async)'];
        yield 'recent message' => [['async' => [1, 60]], [], SettingsResult::GREEN, '1 mins (async)'];
        yield 'exact grace boundary' => [['async' => [1, 900]], [], SettingsResult::GREEN, '15 mins (async)'];
        yield 'just over grace' => [['async' => [1, 960]], [], SettingsResult::WARNING, '16 mins (async)'];
        yield 'negative age' => [['async' => [1, -60]], [], SettingsResult::GREEN, '0 mins (async)'];
        yield 'failed excluded by default' => [['async_failed' => [1, 7200]], [], SettingsResult::INFO, '0 mins'];
        yield 'failed case insensitive' => [['async_FAILED' => [1, 7200]], [], SettingsResult::INFO, '0 mins'];
        yield 'failed explicitly excluded' => [['failed' => [1, 7200]], ['monitorExcludeFailedQueues' => true], SettingsResult::INFO, '0 mins'];
        yield 'failed included' => [['failed' => [1, 7200]], ['monitorExcludeFailedQueues' => false], SettingsResult::WARNING, '120 mins (failed)'];
        yield 'allowlist filters before querying' => [['async' => [1, 60], 'low' => [1, 7200]], ['monitorQueues' => ' async, , '], SettingsResult::GREEN, '1 mins (async)'];
        yield 'custom default grace' => [['low' => [1, 1800]], ['monitorQueueGraceTime' => 60], SettingsResult::GREEN, '30 mins (low)', 'max 60 mins'];
        yield 'queue override' => [['low' => [1, 1800]], ['monitorQueueGraceTimes' => 'low:60, async:10'], SettingsResult::GREEN, '30 mins (low)', 'max 60 mins'];
        yield 'invalid overrides' => [['async' => [1, 1200]], ['monitorQueueGraceTimes' => 'missing, :10, async:no, async:0, async:-5'], SettingsResult::WARNING, '20 mins (async)'];
        yield 'overdue beats older healthy' => [['low' => [1, 5400], 'async' => [1, 1200]], ['monitorQueueGraceTimes' => 'low:120,async:10'], SettingsResult::WARNING, '20 mins (async)', 'max 10 mins'];
        yield 'healthy cannot mask overdue' => [['async' => [1, 1200], 'low' => [1, 5400]], ['monitorQueueGraceTimes' => 'low:120,async:10'], SettingsResult::WARNING, '20 mins (async)', 'max 10 mins'];
        yield 'most overdue wins' => [['first' => [1, 1200], 'second' => [1, 1800]], [], SettingsResult::WARNING, '30 mins (second)'];
        yield 'less overdue does not win' => [['first' => [1, 1800], 'second' => [1, 1200]], [], SettingsResult::WARNING, '30 mins (first)'];
        yield 'overdue tie oldest wins' => [['first' => [1, 1200], 'second' => [1, 1800]], ['monitorQueueGraceTimes' => 'second:25'], SettingsResult::WARNING, '30 mins (second)', 'max 25 mins'];
        yield 'healthy oldest wins' => [['first' => [1, 60], 'second' => [1, 120]], [], SettingsResult::GREEN, '2 mins (second)'];
        yield 'healthy younger does not win' => [['first' => [1, 120], 'second' => [1, 60]], [], SettingsResult::GREEN, '2 mins (first)'];
        yield 'healthy plus unknown' => [['async' => [1, 60], 'amqp' => [1, null]], [], SettingsResult::INFO, '1 mins (async); Age unavailable (amqp)'];
        yield 'overdue plus unknown' => [['async' => [1, 1200], 'amqp' => [1, null]], [], SettingsResult::WARNING, '20 mins (async)'];
        yield 'multiple unknown' => [['a' => [1, null], 'b' => [null, null]], [], SettingsResult::INFO, 'Age unavailable (a, b)'];
    }
}
