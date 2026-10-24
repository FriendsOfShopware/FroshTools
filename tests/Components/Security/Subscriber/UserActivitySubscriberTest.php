<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Subscriber;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\Activity\ActivityActorResolver;
use Frosh\Tools\Components\Security\Activity\PasswordResetContext;
use Frosh\Tools\Components\Security\Subscriber\UserActivitySubscriber;
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
use Symfony\Component\HttpFoundation\RequestStack;

/** @internal */
#[Package('framework')]
#[CoversClass(UserActivitySubscriber::class)]
class UserActivitySubscriberTest extends TestCase
{
    private TestHandler $logs;

    private UserActivitySubscriber $subscriber;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->logs = new TestHandler();
        $this->connection = static::createStub(Connection::class);
        $this->subscriber = new UserActivitySubscriber(new Logger('system_activity', [$this->logs]), new ActivityActorResolver($this->connection), new PasswordResetContext(new RequestStack()));
    }

    public function testRecordsSecurityChangesWithoutSecretsOrUnrelatedUpdates(): void
    {
        $this->subscriber->onEntityWritten(new EntityWrittenEvent('user', [
            new EntityWriteResult('preferences', ['timeZone' => 'UTC'], 'user', EntityWriteResult::OPERATION_UPDATE),
            new EntityWriteResult('target', ['password' => 'secret', 'active' => false, 'admin' => true], 'user', EntityWriteResult::OPERATION_UPDATE),
        ], new Context(new AdminApiSource('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'))));

        $records = $this->logs->getRecords();
        static::assertCount(1, $records);
        static::assertSame('user:update', $records[0]->message);
        static::assertSame(['actorType' => 'user', 'userId' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'entityId' => 'target', 'changedFields' => ['password', 'active', 'admin']], $records[0]->context);
    }

    public function testAccountChangeIsRecordedWhenActorNameLookupFails(): void
    {
        $this->connection->method('fetchOne')->willThrowException(new \RuntimeException('Database unavailable'));

        $this->subscriber->onEntityWritten(new EntityWrittenEvent('user', [
            new EntityWriteResult('target', ['active' => false], 'user', EntityWriteResult::OPERATION_UPDATE),
        ], new Context(new AdminApiSource('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'))));

        $record = $this->logs->getRecords()[0];
        static::assertSame('user:update', $record->message);
        static::assertSame(['actorType' => 'user', 'userId' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'entityId' => 'target', 'changedFields' => ['active']], $record->context);
    }

    public function testLogsNewUsersAndIgnoresUnrelatedUpdates(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(class_exists('Shopware\\Core\\Framework\\Log\\SystemActivitySubscriber') ? $this->never() : $this->once())->method('info')->with('user:create', [
            'entityId' => 'new-user',
            'actorType' => 'system',
        ]);
        $subscriber = new UserActivitySubscriber($logger, new ActivityActorResolver(static::createStub(Connection::class)), new PasswordResetContext(new RequestStack()));
        $event = new EntityWrittenEvent('user', [
            new EntityWriteResult('new-user', ['password' => 'secret'], 'user', EntityWriteResult::OPERATION_INSERT),
            new EntityWriteResult('existing-user', [], 'user', EntityWriteResult::OPERATION_UPDATE),
        ], Context::createDefaultContext());

        $subscriber->onEntityWritten($event);
    }
}
