<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Queue;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Frosh\Tools\Components\Queue\DoctrineQueueAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection as MessengerConnection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

#[CoversClass(DoctrineQueueAdapter::class)]
class DoctrineQueueAdapterTest extends TestCase
{
    private Connection $connection;

    private DoctrineQueueAdapter $adapter;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE custom_messages (queue_name VARCHAR(255), created_at DATETIME, available_at DATETIME, delivered_at DATETIME)');
        $transport = new DoctrineTransport(new MessengerConnection([
            'table_name' => 'custom_messages',
            'queue_name' => 'configured_queue',
        ], $this->connection), new PhpSerializer());
        $this->adapter = new DoctrineQueueAdapter('async', $transport, $this->createMock(MessageBusInterface::class));
    }

    public function testOnlyAvailableUnclaimedMessagesInConfiguredQueueDetermineAge(): void
    {
        $this->insert('configured_queue', '-2 hours', '-20 minutes');
        $this->insert('configured_queue', '-5 hours', '+1 hour');
        $this->insert('configured_queue', '-6 hours', '-6 hours', 'now');
        $this->insert('unused_queue', '-90 days', '-90 days');

        $age = $this->adapter->getOldestMessageAge();

        static::assertNotNull($age);
        static::assertGreaterThanOrEqual(1200, $age);
        static::assertLessThan(1205, $age);
    }

    public function testEmptyQueueHasNoAge(): void
    {
        static::assertNull($this->adapter->getOldestMessageAge());
    }

    public function testFutureMessagesHaveNoPendingAge(): void
    {
        $this->insert('configured_queue', '-5 hours', '+1 hour');

        static::assertNull($this->adapter->getOldestMessageAge());
    }

    public function testInspectionFailureHasUnknownAge(): void
    {
        $this->connection->executeStatement('DROP TABLE custom_messages');

        static::assertNull($this->adapter->getOldestMessageAge());
    }

    private function insert(string $queue, string $created, string $available, ?string $delivered = null): void
    {
        $utc = new \DateTimeZone('UTC');
        $this->connection->insert('custom_messages', [
            'queue_name' => $queue,
            'created_at' => (new \DateTimeImmutable($created, $utc))->format('Y-m-d H:i:s'),
            'available_at' => (new \DateTimeImmutable($available, $utc))->format('Y-m-d H:i:s'),
            'delivered_at' => null === $delivered ? null : (new \DateTimeImmutable($delivered, $utc))->format('Y-m-d H:i:s'),
        ]);
    }
}
