<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Queue;

use Doctrine\DBAL\Connection as DbalConnection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection as MessengerConnection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * Specialization for the Doctrine transport: purges with a single DELETE instead of
 * rejecting message by message and reads the age of the oldest waiting message via SQL.
 */
class DoctrineQueueAdapter extends ListableQueueAdapter
{
    public function __construct(
        string $name,
        private readonly DoctrineTransport $doctrineTransport,
        MessageBusInterface $bus,
    ) {
        parent::__construct($name, $doctrineTransport, $bus);
    }

    public static function supports(ReceiverInterface $transport): bool
    {
        return class_exists(DoctrineTransport::class) && $transport instanceof DoctrineTransport;
    }

    /**
     * Queue name used by existing health-monitor settings, which may differ from the transport alias.
     */
    public function getQueueName(): string
    {
        return $this->getMessengerConnection()->getConfiguration()['queue_name'];
    }

    public function getOldestMessageAge(): ?int
    {
        try {
            $configuration = $this->getMessengerConnection()->getConfiguration();

            $availableAt = $this->getDbalConnection()->fetchOne(
                \sprintf('SELECT MIN(available_at) FROM %s WHERE queue_name = ? AND delivered_at IS NULL AND available_at <= ?', $configuration['table_name']),
                [$configuration['queue_name'], (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s')],
            );

            if (!\is_string($availableAt) || $availableAt === '') {
                return null;
            }

            $age = time() - (new \DateTimeImmutable($availableAt, new \DateTimeZone('UTC')))->getTimestamp();

            return max(0, $age);
        } catch (\Throwable) {
            return null;
        }
    }

    public function purge(): void
    {
        $configuration = $this->getMessengerConnection()->getConfiguration();

        $this->getDbalConnection()->executeStatement(
            \sprintf('DELETE FROM %s WHERE queue_name = ?', $configuration['table_name']),
            [$configuration['queue_name']],
        );
    }

    private function getMessengerConnection(): MessengerConnection
    {
        $transport = $this->doctrineTransport;
        $getter = \Closure::bind(fn () => $transport->connection, $transport, $transport::class);

        return $getter();
    }

    private function getDbalConnection(): DbalConnection
    {
        $connection = $this->getMessengerConnection();
        // @phpstan-ignore-next-line property.notFound
        $getter = \Closure::bind(fn () => $connection->driverConnection, $connection, $connection::class);

        return $getter();
    }
}
