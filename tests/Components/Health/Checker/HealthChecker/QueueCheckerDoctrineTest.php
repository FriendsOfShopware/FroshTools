<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\HealthChecker;

use Doctrine\DBAL\DriverManager;
use Frosh\Tools\Components\Health\Checker\HealthChecker\QueueChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Components\Queue\DoctrineQueueAdapter;
use Frosh\Tools\Components\Queue\QueueRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection as MessengerConnection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

#[CoversClass(QueueChecker::class)]
#[CoversClass(DoctrineQueueAdapter::class)]
class QueueCheckerDoctrineTest extends TestCase
{
    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('savedQueueSettings')]
    public function testSavedDoctrineQueueSettings(string $transportName, string $queueName, array $config, string $state, string $current, string $recommended): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE custom_messages (queue_name VARCHAR(255), available_at DATETIME, delivered_at DATETIME)');
        $connection->insert('custom_messages', [
            'queue_name' => $queueName,
            'available_at' => (new \DateTimeImmutable('-20 minutes', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ]);
        $transport = new DoctrineTransport(new MessengerConnection([
            'table_name' => 'custom_messages',
            'queue_name' => $queueName,
        ], $connection), new PhpSerializer());
        $registry = new QueueRegistry(new ServiceLocator([
            $transportName => static fn () => $transport,
        ]), $this->createMock(MessageBusInterface::class));
        // Health monitoring must keep the transport identity used by the queue overview intact.
        static::assertSame($transportName, $registry->get($transportName)->getName());

        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('getInt')->willReturnCallback(static fn (string $key): int => (int) ($config[substr($key, 18)] ?? 0));
        $configService->method('getString')->willReturnCallback(static fn (string $key): string => (string) ($config[substr($key, 18)] ?? ''));
        $configService->method('get')->willReturnCallback(static fn (string $key): mixed => $config[substr($key, 18)] ?? null);
        $collection = new HealthCollection();
        (new QueueChecker($registry, $configService))->collect($collection);
        $results = array_values($collection->getElements());

        static::assertCount(1, $results);
        static::assertSame($state, $results[0]->state);
        static::assertSame($current, $results[0]->current);
        static::assertSame($recommended, $results[0]->recommended);
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>, string, string, string}>
     */
    public static function savedQueueSettings(): iterable
    {
        yield 'legacy allowlist includes database queue' => ['async', 'storefront', ['monitorQueues' => 'storefront'], SettingsResult::WARNING, '20 mins (storefront)', 'max 15 mins'];
        yield 'legacy allowlist excludes other database queues' => ['async', 'storefront', ['monitorQueues' => 'other'], SettingsResult::INFO, '0 mins', 'max 15 mins'];
        yield 'transport alias does not override legacy allowlist' => ['async', 'storefront', ['monitorQueues' => 'async'], SettingsResult::INFO, '0 mins', 'max 15 mins'];
        yield 'legacy tighter queue grace' => ['async', 'storefront', ['monitorQueueGraceTime' => 60, 'monitorQueueGraceTimes' => 'storefront:10'], SettingsResult::WARNING, '20 mins (storefront)', 'max 10 mins'];
        yield 'legacy looser queue grace' => ['async', 'storefront', ['monitorQueueGraceTimes' => 'storefront:60'], SettingsResult::GREEN, '20 mins (storefront)', 'max 60 mins'];
        yield 'queue grace takes precedence over transport setting' => ['async', 'storefront', ['monitorQueueGraceTimes' => 'async:60,storefront:10'], SettingsResult::WARNING, '20 mins (storefront)', 'max 10 mins'];
        yield 'legacy failed queue excluded despite neutral transport name' => ['errors', 'storefront_failed', [], SettingsResult::INFO, '0 mins', 'max 15 mins'];
        yield 'legacy failed queue included when enabled' => ['errors', 'storefront_failed', ['monitorExcludeFailedQueues' => false], SettingsResult::WARNING, '20 mins (storefront_failed)', 'max 15 mins'];
        yield 'transport name does not falsely mark a normal queue as failed' => ['failed_transport', 'storefront', [], SettingsResult::WARNING, '20 mins (storefront)', 'max 15 mins'];
        yield 'combined legacy settings' => ['async', 'storefront', ['monitorQueues' => 'storefront, other', 'monitorQueueGraceTimes' => 'storefront:10'], SettingsResult::WARNING, '20 mins (storefront)', 'max 10 mins'];
    }
}
