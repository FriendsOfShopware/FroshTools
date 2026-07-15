<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Queue;

use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Browses the Redis stream transport (symfony/redis-messenger) via XREVRANGE, so messages
 * are shown without being consumed. Internals are accessed the same way CacheAdapter
 * unwraps cache pools, as the transport does not expose them.
 */
class RedisQueueAdapter implements QueueAdapter
{
    private const REDIS_TRANSPORT_CLASS = 'Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransport';

    public function __construct(
        private readonly string $name,
        private readonly ReceiverInterface $transport,
    ) {
    }

    public static function supports(ReceiverInterface $transport): bool
    {
        return class_exists(self::REDIS_TRANSPORT_CLASS) && is_a($transport, self::REDIS_TRANSPORT_CLASS);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return 'Redis';
    }

    public function getMessageCount(): ?int
    {
        if ($this->transport instanceof MessageCountAwareInterface) {
            try {
                return $this->transport->getMessageCount();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    public function getOldestMessageAge(): ?int
    {
        try {
            $connection = $this->extractProperty($this->transport, 'connection');
            \assert(\is_object($connection));
            $redis = $this->getRedis($connection);
            $stream = $this->extractProperty($connection, 'stream');

            $entries = $redis->xRange($stream, '-', '+', 1);
            if (!\is_array($entries) || $entries === []) {
                return null;
            }

            // Stream ids are "<milliseconds>-<sequence>"
            $milliseconds = (int) explode('-', (string) array_key_first($entries))[0];
            if ($milliseconds <= 0) {
                return null;
            }

            return max(0, time() - intdiv($milliseconds, 1000));
        } catch (\Throwable) {
            return null;
        }
    }

    public function isBrowsable(): bool
    {
        return true;
    }

    public function requeuesOnBrowse(): bool
    {
        return false;
    }

    public function supportsRemove(): bool
    {
        return false;
    }

    public function removeMessage(string $id): void
    {
        throw new \RuntimeException(\sprintf('The transport "%s" does not support removing single messages', $this->name));
    }

    public function supportsRetry(): bool
    {
        return false;
    }

    public function retryMessage(string $id): void
    {
        throw new \RuntimeException(\sprintf('The transport "%s" does not support retrying messages', $this->name));
    }

    public function supportsPurge(): bool
    {
        return true;
    }

    public function purge(): void
    {
        $connection = $this->extractProperty($this->transport, 'connection');
        \assert(\is_object($connection));
        $redis = $this->getRedis($connection);

        // Empty the stream but keep it (and its consumer group) alive, then drop the
        // sorted set holding delayed messages
        $redis->xTrim($this->extractProperty($connection, 'stream'), 0);
        $redis->del($this->extractProperty($connection, 'queue'));
    }

    public function getMessages(int $limit): array
    {
        $connection = $this->extractProperty($this->transport, 'connection');
        $serializer = $this->extractProperty($this->transport, 'serializer');
        \assert($serializer instanceof SerializerInterface);

        $redis = $this->getRedis($connection);
        $stream = $this->extractProperty($connection, 'stream');

        $entries = $redis->xRevRange($stream, '+', '-', $limit);
        if (!\is_array($entries)) {
            return [];
        }

        $messages = [];
        foreach ($entries as $id => $entry) {
            $messages[] = $this->decodeEntry((string) $id, $entry, $serializer);
        }

        return $messages;
    }

    /**
     * @param array<string, string> $entry
     */
    private function decodeEntry(string $id, array $entry, SerializerInterface $serializer): QueueMessage
    {
        $raw = $entry['message'] ?? '';

        try {
            $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
            $envelope = $serializer->decode([
                'body' => $decoded['body'],
                'headers' => $decoded['headers'] ?? [],
            ]);

            return QueueMessage::fromEnvelope($envelope, $id);
        } catch (\Throwable) {
            return QueueMessage::raw($id, $raw);
        }
    }

    private function getRedis(object $connection): \Redis|\RedisCluster
    {
        $getter = \Closure::bind(function () {
            // @phpstan-ignore-next-line property.notFound
            return $this->redis instanceof \Closure ? ($this->redis)() : $this->redis;
        }, $connection, $connection::class);

        return $getter();
    }

    private function extractProperty(object $object, string $property): mixed
    {
        $getter = \Closure::bind(fn () => $this->{$property}, $object, $object::class);

        return $getter();
    }
}
