<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Queue;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

/**
 * Works for every transport implementing ListableReceiverInterface (e.g. the Doctrine
 * transport used by Shopware by default), which allows listing messages non-destructively.
 */
class ListableQueueAdapter implements QueueAdapter
{
    private const PURGE_BATCH_SIZE = 100;

    public function __construct(
        private readonly string $name,
        private readonly ListableReceiverInterface $transport,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        $parts = explode('\\', $this->transport::class);
        $shortName = end($parts);

        return str_ends_with($shortName, 'Transport') ? substr($shortName, 0, -\strlen('Transport')) : $shortName;
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
        return false;
    }

    public function getMessages(int $limit): array
    {
        $messages = [];

        foreach ($this->transport->all($limit) as $envelope) {
            $messages[] = QueueMessage::fromEnvelope($envelope);
        }

        return $messages;
    }

    public function supportsRemove(): bool
    {
        return true;
    }

    public function removeMessage(string $id): void
    {
        $this->transport->reject($this->findEnvelope($id));
    }

    public function supportsRetry(): bool
    {
        return true;
    }

    public function retryMessage(string $id): void
    {
        $envelope = $this->findEnvelope($id);

        $failureStamp = $envelope->last(SentToFailureTransportStamp::class);
        if ($failureStamp === null) {
            throw new \RuntimeException(\sprintf('Message "%s" is not a failed message, only failed messages can be retried', $id));
        }

        // A fresh envelope on purpose: carrying over stamps like ReceivedStamp would make
        // the bus handle the message synchronously instead of sending it to the transport
        $this->bus->dispatch(new Envelope($envelope->getMessage(), [
            new TransportNamesStamp([$failureStamp->getOriginalReceiverName()]),
        ]));

        $this->transport->reject($envelope);
    }

    public function supportsPurge(): bool
    {
        return true;
    }

    public function purge(): void
    {
        do {
            $rejected = 0;
            foreach ($this->transport->all(self::PURGE_BATCH_SIZE) as $envelope) {
                $this->transport->reject($envelope);
                ++$rejected;
            }
        } while ($rejected === self::PURGE_BATCH_SIZE);
    }

    private function findEnvelope(string $id): Envelope
    {
        $envelope = $this->transport->find($id);

        if ($envelope === null) {
            throw new \RuntimeException(\sprintf('Message "%s" was not found in transport "%s", it may have been processed already', $id, $this->name));
        }

        return $envelope;
    }
}
