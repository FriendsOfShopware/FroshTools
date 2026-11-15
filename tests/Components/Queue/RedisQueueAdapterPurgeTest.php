<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Queue;

use Frosh\Tools\Components\Queue\RedisQueueAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

#[CoversClass(RedisQueueAdapter::class)]
class RedisQueueAdapterPurgeTest extends TestCase
{
    public function testPurgeTrimsTheStreamAndDeletesOnlyTheDelayedQueue(): void
    {
        $redis = $this->createMock(\Redis::class);
        $threshold = version_compare((string) phpversion('redis'), '6.0.0', '>=') ? '0' : 0;
        $redis->expects(static::once())->method('xtrim')->with('stream', static::identicalTo($threshold))->willReturn(2);
        $redis->expects(static::once())->method('del')->with('delayed')->willReturn(1);

        $connection = new class($redis) {
            private string $stream = 'stream';

            private string $queue = 'delayed';

            public function __construct(private readonly \Redis $redis)
            {
            }
        };
        $transport = new class($connection) implements ReceiverInterface {
            public function __construct(private readonly object $connection)
            {
            }

            public function get(): iterable
            {
                return [];
            }

            public function ack(Envelope $envelope): void
            {
            }

            public function reject(Envelope $envelope): void
            {
            }
        };

        $adapter = new RedisQueueAdapter('redis', $transport);
        $adapter->purge();
    }
}
