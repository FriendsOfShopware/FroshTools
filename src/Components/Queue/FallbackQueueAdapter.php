<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Queue;

use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * Used for transports we cannot browse (e.g. AMQP, sync, in-memory). Only exposes the
 * message count when the transport supports it.
 */
class FallbackQueueAdapter implements QueueAdapter
{
    public function __construct(
        private readonly string $name,
        private readonly ReceiverInterface $transport,
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
        return false;
    }

    public function requeuesOnBrowse(): bool
    {
        return false;
    }

    public function getMessages(int $limit): array
    {
        throw new \RuntimeException(\sprintf('The transport "%s" (%s) does not support browsing messages', $this->name, $this->getType()));
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
        return false;
    }

    public function purge(): void
    {
        throw new \RuntimeException(\sprintf('The transport "%s" does not support purging', $this->name));
    }
}
