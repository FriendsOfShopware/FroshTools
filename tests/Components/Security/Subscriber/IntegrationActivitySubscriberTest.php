<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Subscriber;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\Activity\ActivityActorResolver;
use Frosh\Tools\Components\Security\Subscriber\IntegrationActivitySubscriber;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;

/** @internal */
#[Package('framework')]
#[CoversClass(IntegrationActivitySubscriber::class)]
class IntegrationActivitySubscriberTest extends TestCase
{
    private TestHandler $logs;

    private IntegrationActivitySubscriber $subscriber;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->logs = new TestHandler();
        $this->connection = static::createStub(Connection::class);
        $this->subscriber = new IntegrationActivitySubscriber(new Logger('system_activity', [$this->logs]), new ActivityActorResolver($this->connection));
    }

    public function testIntegrationRenameRetainsLabelWithoutCredentials(): void
    {
        $this->subscriber->onEntityWritten(new EntityWrittenEvent('integration', [
            new EntityWriteResult('target', ['label' => 'Renamed integration', 'secretAccessKey' => 'secret'], 'integration', EntityWriteResult::OPERATION_UPDATE),
        ], Context::createDefaultContext()));

        $record = $this->logs->getRecords()[0];
        static::assertSame('integration:update', $record->message);
        static::assertSame('Renamed integration', $record->context['integrationName']);
        static::assertSame('target', $record->context['entityId']);
        static::assertArrayNotHasKey('secretAccessKey', $record->context);
    }

    public function testIntegrationChangeIsRecordedWhenActorNameLookupFails(): void
    {
        $this->connection->method('fetchOne')->willThrowException(new \RuntimeException('Database unavailable'));

        $this->subscriber->onEntityWritten(new EntityWrittenEvent('integration', [
            new EntityWriteResult('target', ['admin' => false], 'integration', EntityWriteResult::OPERATION_UPDATE),
        ], new Context(new AdminApiSource(null, 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'))));

        $record = $this->logs->getRecords()[0];
        static::assertSame('integration:update', $record->message);
        static::assertSame(['actorType' => 'integration', 'integrationId' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'entityId' => 'target', 'changedFields' => ['admin']], $record->context);
    }

    public function testIntegrationCreationRetainsLabelWithoutCredentials(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info')->with('integration:create', [
            'entityId' => 'new-integration',
            'integrationName' => 'Warehouse sync',
            'actorType' => 'system',
        ]);
        $subscriber = new IntegrationActivitySubscriber($logger, new ActivityActorResolver(static::createStub(Connection::class)));
        $subscriber->onEntityWritten(new EntityWrittenEvent('integration', [
            new EntityWriteResult('new-integration', ['label' => 'Warehouse sync', 'secretAccessKey' => 'secret'], 'integration', EntityWriteResult::OPERATION_INSERT),
        ], Context::createDefaultContext()));
    }
}
