<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Queue;

use Frosh\Tools\Components\Queue\RedisQueueAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

#[CoversClass(RedisQueueAdapter::class)]
class RedisQueueAdapterTest extends TestCase
{
    public function testBrowsingStartsAfterTheLastDeliveredMessage(): void
    {
        $redis = new QueueAdapterRedis();
        $redis->groups = [['name' => 'workers', 'last-delivered-id' => '123-4']];
        $redis->reverseEntries = [
            '125-0' => ['message' => 'newest waiting message'],
            '124-0' => ['message' => 'oldest waiting message'],
        ];
        $adapter = $this->createAdapter($redis);

        $messages = $adapter->getMessages(10);

        static::assertSame(['stream', '+', '123-5', 10], $redis->lastReverseRange);
        static::assertSame(['125-0', '124-0'], array_map(static fn ($message) => $message->id, $messages));
    }

    public function testOldestAgeUsesOnlyUndeliveredMessages(): void
    {
        $milliseconds = (time() - 60) * 1000;
        $redis = new QueueAdapterRedis();
        $redis->groups = [['name' => 'workers', 'last-delivered-id' => '123-4']];
        $redis->rangeEntries = [
            $milliseconds . '-0' => ['message' => 'oldest waiting message'],
        ];
        $adapter = $this->createAdapter($redis);

        $age = $adapter->getOldestMessageAge();

        static::assertSame(['stream', '123-5', '+', 1], $redis->lastRange);
        static::assertNotNull($age);
        static::assertGreaterThanOrEqual(60, $age);
        static::assertLessThanOrEqual(61, $age);
    }

    private function createAdapter(QueueAdapterRedis $redis): RedisQueueAdapter
    {
        $serializer = static::createStub(SerializerInterface::class);
        $serializer->method('decode')->willReturn(new Envelope(new \stdClass()));

        return new RedisQueueAdapter('redis', new QueueAdapterReceiver(new QueueAdapterConnection($redis), $serializer));
    }
}

class QueueAdapterRedis extends \Redis
{
    /**
     * @var list<array<string, string>>
     */
    public array $groups = [];

    /**
     * @var array<string, array<string, string>>
     */
    public array $rangeEntries = [];

    /**
     * @var array<string, array<string, string>>
     */
    public array $reverseEntries = [];

    /**
     * @var array{string, string, string, int}|null
     */
    public ?array $lastRange = null;

    /**
     * @var array{string, string, string, int}|null
     */
    public ?array $lastReverseRange = null;

    public function xinfo(string $operation, ?string $arg1 = null, ?string $arg2 = null, int $count = -1): mixed
    {
        return $this->groups;
    }

    public function xrange(string $key, string $start, string $end, int $count = -1): \Redis|array|bool
    {
        $this->lastRange = [$key, $start, $end, $count];

        return $this->rangeEntries;
    }

    public function xrevrange(string $key, string $end, string $start, int $count = -1): \Redis|array|bool
    {
        $this->lastReverseRange = [$key, $end, $start, $count];

        return $this->reverseEntries;
    }
}

class QueueAdapterConnection
{
    private string $stream = 'stream';

    private string $group = 'workers';

    public function __construct(private readonly QueueAdapterRedis $redis)
    {
    }
}

class QueueAdapterReceiver implements ReceiverInterface
{
    public function __construct(
        private readonly QueueAdapterConnection $connection,
        private readonly SerializerInterface $serializer,
    ) {
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
}
