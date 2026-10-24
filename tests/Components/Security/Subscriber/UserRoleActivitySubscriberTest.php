<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Subscriber;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\Activity\ActivityActorResolver;
use Frosh\Tools\Components\Security\Subscriber\UserRoleActivitySubscriber;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\Log\Package;

/** @internal */
#[Package('framework')]
#[CoversClass(UserRoleActivitySubscriber::class)]
class UserRoleActivitySubscriberTest extends TestCase
{
    private TestHandler $logs;

    private UserRoleActivitySubscriber $subscriber;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->logs = new TestHandler();
        $this->connection = static::createStub(Connection::class);
        $this->subscriber = new UserRoleActivitySubscriber(new Logger('system_activity', [$this->logs]), new ActivityActorResolver($this->connection));
    }

    public function testRecordsRemovedRoleAssignmentWithTargetAndActor(): void
    {
        $this->subscriber->onEntityWritten(new EntityDeletedEvent('acl_user_role', [
            new EntityWriteResult(['userId' => 'target', 'aclRoleId' => 'role'], [], 'acl_user_role', EntityWriteResult::OPERATION_DELETE),
        ], new Context(new AdminApiSource(null, 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'))));

        $record = $this->logs->getRecords()[0];
        static::assertSame('acl_user_role:delete', $record->message);
        static::assertSame(['actorType' => 'integration', 'integrationId' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'targetUserId' => 'target', 'roleId' => 'role', 'changedFields' => []], $record->context);
    }
}
