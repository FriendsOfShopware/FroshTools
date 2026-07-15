<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Queue;

use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Browses the AMQP transport (symfony/amqp-messenger). AMQP has no passive peek, so this
 * does what the RabbitMQ management UI does: basic.get without auto-ack, then nack with
 * requeue. Browsed messages are marked as redelivered and ordering may change.
 */
class AmqpQueueAdapter implements QueueAdapter
{
    private const AMQP_TRANSPORT_CLASS = 'Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport';

    public function __construct(
        private readonly string $name,
        private readonly ReceiverInterface $transport,
    ) {
    }

    public static function supports(ReceiverInterface $transport): bool
    {
        return class_exists(self::AMQP_TRANSPORT_CLASS) && is_a($transport, self::AMQP_TRANSPORT_CLASS);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return 'AMQP';
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
        return null;
    }

    public function isBrowsable(): bool
    {
        return true;
    }

    public function requeuesOnBrowse(): bool
    {
        return true;
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

        // @phpstan-ignore-next-line method.notFound
        $connection->purgeQueues();
    }

    public function getMessages(int $limit): array
    {
        $connection = $this->extractProperty($this->transport, 'connection');
        \assert(\is_object($connection));
        $serializer = $this->extractProperty($this->transport, 'serializer');
        \assert($serializer instanceof SerializerInterface);

        /** @var list<array{string, \AMQPEnvelope}> $fetched */
        $fetched = [];

        try {
            // @phpstan-ignore-next-line method.notFound
            foreach ($connection->getQueueNames() as $queueName) {
                // @phpstan-ignore-next-line method.notFound
                while (\count($fetched) < $limit && ($amqpEnvelope = $connection->get($queueName)) !== null) {
                    $fetched[] = [$queueName, $amqpEnvelope];
                }

                if (\count($fetched) >= $limit) {
                    break;
                }
            }
        } finally {
            // Put every fetched message back, even if fetching failed halfway through
            foreach ($fetched as [$queueName, $amqpEnvelope]) {
                try {
                    // @phpstan-ignore-next-line method.notFound
                    $connection->nack($amqpEnvelope, $queueName, \AMQP_REQUEUE);
                } catch (\Throwable) {
                }
            }
        }

        $messages = [];
        foreach ($fetched as [, $amqpEnvelope]) {
            $messages[] = $this->decodeEnvelope($amqpEnvelope, $serializer);
        }

        return $messages;
    }

    private function decodeEnvelope(\AMQPEnvelope $amqpEnvelope, SerializerInterface $serializer): QueueMessage
    {
        $id = $amqpEnvelope->getMessageId() ?: (string) $amqpEnvelope->getDeliveryTag();

        try {
            $envelope = $serializer->decode([
                'body' => $amqpEnvelope->getBody(),
                'headers' => $amqpEnvelope->getHeaders(),
            ]);

            return QueueMessage::fromEnvelope($envelope, $id);
        } catch (\Throwable) {
            return QueueMessage::raw($id, (string) $amqpEnvelope->getBody());
        }
    }

    private function extractProperty(object $object, string $property): mixed
    {
        $getter = \Closure::bind(fn () => $this->{$property}, $object, $object::class);

        return $getter();
    }
}
