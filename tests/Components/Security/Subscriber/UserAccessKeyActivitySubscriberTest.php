<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Subscriber;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\Activity\ActivityActorResolver;
use Frosh\Tools\Components\Security\Subscriber\UserAccessKeyActivitySubscriber;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;

/** @internal */
#[Package('framework')]
#[CoversClass(UserAccessKeyActivitySubscriber::class)]
class UserAccessKeyActivitySubscriberTest extends TestCase
{
    private TestHandler $logs;

    private UserAccessKeyActivitySubscriber $subscriber;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->logs = new TestHandler();
        $this->connection = static::createStub(Connection::class);
        $this->subscriber = new UserAccessKeyActivitySubscriber(new Logger('system_activity', [$this->logs]), new ActivityActorResolver($this->connection));
    }

    #[DataProvider('accessKeyWrites')]
    public function testAccessKeyChangesRetainTheirOwnerAndActor(string $operation, string $action): void
    {
        $this->subscriber->onEntityWritten(new EntityWrittenEvent('user_access_key', [
            new EntityWriteResult('key', ['userId' => 'owner', 'secretAccessKey' => 'secret'], 'user_access_key', $operation),
        ], new Context(new AdminApiSource('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'))));

        $record = $this->logs->getRecords()[0];
        static::assertSame('user_access_key:' . $action, $record->message);
        static::assertSame('owner', $record->context['targetUserId']);
        static::assertSame('key', $record->context['entityId']);
        static::assertSame('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $record->context['userId']);
        static::assertArrayNotHasKey('secretAccessKey', $record->context);
    }

    public static function accessKeyWrites(): iterable
    {
        yield 'create key for another user' => [EntityWriteResult::OPERATION_INSERT, 'create'];
        yield 'change key owner' => [EntityWriteResult::OPERATION_UPDATE, 'update'];
    }
}
