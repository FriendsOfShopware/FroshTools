<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Queue;

/**
 * Abstraction over a single messenger transport, similar to CacheAdapter for cache pools.
 * Implementations know how to inspect a specific transport type without consuming messages.
 */
interface QueueAdapter
{
    public function getName(): string;

    public function getType(): string;

    public function getMessageCount(): ?int;

    /**
     * Age of the oldest waiting message in seconds, null when the transport cannot tell.
     */
    public function getOldestMessageAge(): ?int;

    public function isBrowsable(): bool;

    /**
     * Whether browsing consumes messages and puts them back (e.g. AMQP) instead of a
     * passive peek. Requeued messages are marked as redelivered and ordering may change.
     */
    public function requeuesOnBrowse(): bool;

    /**
     * Returns up to $limit pending messages without permanently removing them from the queue.
     *
     * @return QueueMessage[]
     */
    public function getMessages(int $limit): array;

    public function supportsRemove(): bool;

    public function removeMessage(string $id): void;

    /**
     * Re-dispatches a message from a failure transport to its original transport and
     * removes it here. Only valid for messages carrying a SentToFailureTransportStamp.
     */
    public function supportsRetry(): bool;

    public function retryMessage(string $id): void;

    public function supportsPurge(): bool;

    public function purge(): void;
}
