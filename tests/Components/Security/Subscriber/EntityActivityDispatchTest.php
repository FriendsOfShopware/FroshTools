<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Subscriber;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\Activity\ActivityActorResolver;
use Frosh\Tools\Components\Security\Activity\PasswordResetContext;
use Frosh\Tools\Components\Security\Subscriber\CustomerActivitySubscriber;
use Frosh\Tools\Components\Security\Subscriber\IntegrationActivitySubscriber;
use Frosh\Tools\Components\Security\Subscriber\IntegrationRoleActivitySubscriber;
use Frosh\Tools\Components\Security\Subscriber\RoleActivitySubscriber;
use Frosh\Tools\Components\Security\Subscriber\UserAccessKeyActivitySubscriber;
use Frosh\Tools\Components\Security\Subscriber\UserActivitySubscriber;
use Frosh\Tools\Components\Security\Subscriber\UserRoleActivitySubscriber;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/** @internal */
class EntityActivityDispatchTest extends TestCase
{
    #[DataProvider('writes')]
    public function testEachWriteIsOwnedByExactlyOneSubscriber(string $entity, string|array $key, array $payload, ?string $route, string $expectedAction, array $expectedDetails): void
    {
        $logs = new TestHandler();
        $logger = new Logger('system_activity', [$logs]);
        $actors = new ActivityActorResolver(static::createStub(Connection::class));
        $requests = new RequestStack();
        $requests->push(new Request(attributes: ['_route' => $route]));
        $resets = new PasswordResetContext($requests);
        $dispatcher = new EventDispatcher();
        foreach ([
            new UserActivitySubscriber($logger, $actors, $resets),
            new CustomerActivitySubscriber($logger, $actors, $resets),
            new IntegrationActivitySubscriber($logger, $actors),
            new RoleActivitySubscriber($logger, $actors),
            new UserRoleActivitySubscriber($logger, $actors),
            new IntegrationRoleActivitySubscriber($logger, $actors),
            new UserAccessKeyActivitySubscriber($logger, $actors),
        ] as $subscriber) {
            $dispatcher->addSubscriber($subscriber);
        }
        $dispatcher->dispatch(new EntityWrittenEvent($entity, [
            new EntityWriteResult($key, $payload, $entity, EntityWriteResult::OPERATION_UPDATE),
        ], Context::createDefaultContext()), $entity . '.written');
        static::assertCount(1, $logs->getRecords());
        $record = $logs->getRecords()[0];
        static::assertSame($expectedAction, $record->message);
        foreach ($expectedDetails as $field => $value) {
            static::assertSame($value, $record->context[$field]);
        }
        static::assertArrayNotHasKey('password', $record->context);
        static::assertArrayNotHasKey('privileges', $record->context);
    }

    public static function writes(): iterable
    {
        yield 'user profile' => ['user', 'user', ['active' => false], null, 'user:update', ['entityId' => 'user']];
        yield 'integration label' => ['integration', 'integration', ['label' => 'Warehouse'], null, 'integration:update', ['integrationName' => 'Warehouse']];
        yield 'user key' => ['user_access_key', 'key', ['userId' => 'owner'], null, 'user_access_key:update', ['targetUserId' => 'owner']];
        yield 'role privileges' => ['acl_role', 'role', ['privileges' => ['secret']], null, 'acl_role:update', ['entityId' => 'role']];
        yield 'user role' => ['acl_user_role', ['userId' => 'user', 'aclRoleId' => 'role'], ['aclRoleId' => 'role'], null, 'acl_user_role:update', ['targetUserId' => 'user', 'roleId' => 'role']];
        yield 'integration role' => ['integration_role', ['integrationId' => 'integration', 'aclRoleId' => 'role'], ['aclRoleId' => 'role'], null, 'integration_role:update', ['entityId' => 'integration', 'roleId' => 'role']];
        yield 'user reset' => ['user', 'user', ['password' => 'secret'], 'api.action.user.user-recovery.password', 'user:password_reset', ['actorType' => 'recovery']];
        yield 'customer reset' => ['customer', 'customer', ['password' => 'secret'], 'store-api.account.recovery.password', 'customer:password_reset', ['actorType' => 'recovery']];
        yield 'ordinary customer password change' => ['customer', 'customer', ['password' => 'secret'], null, 'customer:update', ['actorType' => 'system']];
    }
}
