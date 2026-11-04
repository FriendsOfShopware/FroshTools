<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Queue;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Frosh\Tools\Components\Queue\DoctrineQueueAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
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
        $this->connection->executeStatement('CREATE TABLE custom_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, body TEXT, headers TEXT, queue_name VARCHAR(255), created_at DATETIME NULL, available_at DATETIME, delivered_at DATETIME)');
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

    public function testMessagesCarryTheCreatedAtOfTheirRow(): void
    {
        $this->insertMessage('2026-11-04 10:15:30');

        $messages = $this->adapter->getMessages(10);

        static::assertCount(1, $messages);
        static::assertSame('2026-11-04T10:15:30+00:00', $messages[0]->timestamp);
    }

    public function testMessageWithoutCreatedAtHasNoTimestamp(): void
    {
        $this->insertMessage('2026-11-04 10:15:30');
        $this->insertMessage(null);

        $timestamps = array_map(static fn ($message) => $message->timestamp, $this->adapter->getMessages(10));
        sort($timestamps);

        static::assertCount(2, $timestamps);
        static::assertContains('2026-11-04T10:15:30+00:00', $timestamps);
        static::assertContains(null, $timestamps);
    }

    public function testMessagesAreStillListedWhenTimestampLookupFails(): void
    {
        $this->insertMessage('2026-11-04 10:15:30');
        // dropping created_at makes the lookup fail while the transport read keeps working
        $this->connection->executeStatement('ALTER TABLE custom_messages DROP COLUMN created_at');

        $messages = $this->adapter->getMessages(10);

        static::assertCount(1, $messages);
        static::assertNull($messages[0]->timestamp);
    }

    private function insertMessage(?string $createdAt): void
    {
        $encoded = (new PhpSerializer())->encode(new Envelope(new \stdClass()));
        $this->connection->insert('custom_messages', [
            'body' => $encoded['body'],
            'headers' => json_encode($encoded['headers'] ?? [], \JSON_THROW_ON_ERROR),
            'queue_name' => 'configured_queue',
            'created_at' => $createdAt,
            'available_at' => '2026-11-04 00:00:00',
        ]);
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
