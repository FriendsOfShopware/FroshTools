<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Subscriber;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\Subscriber\SecurityActivitySubscriber;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SecurityActivitySubscriber::class)]
class SecurityActivitySubscriberTest extends TestCase
{
    private TestHandler $logs;

    private SecurityActivitySubscriber $subscriber;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->logs = new TestHandler();
        $this->connection = static::createStub(Connection::class);
        $this->subscriber = new SecurityActivitySubscriber(new Logger('system_activity', [$this->logs]), $this->connection, new RequestStack());
    }

    public function testRecordsSecurityChangesWithoutSecretsOrDuplicateCreationEvents(): void
    {
        $this->subscriber->onEntityWritten(new EntityWrittenEvent('user', [
            new EntityWriteResult('new', ['password' => 'secret'], 'user', EntityWriteResult::OPERATION_INSERT),
            new EntityWriteResult('preferences', ['timeZone' => 'UTC'], 'user', EntityWriteResult::OPERATION_UPDATE),
            new EntityWriteResult('target', ['password' => 'secret', 'active' => false, 'admin' => true], 'user', EntityWriteResult::OPERATION_UPDATE),
        ], new Context(new AdminApiSource('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'))));

        $records = $this->logs->getRecords();
        static::assertCount(1, $records);
        static::assertSame('user:update', $records[0]->message);
        static::assertSame(['actorType' => 'user', 'userId' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'entityId' => 'target', 'changedFields' => ['password', 'active', 'admin']], $records[0]->context);
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

    public function testSuccessfulLoginIsRecordedWhenIdentityLookupFails(): void
    {
        $this->connection->method('fetchOne')->willThrowException(new \RuntimeException('Database unavailable'));
        $request = new Request(request: ['grant_type' => 'password', 'client_id' => 'administration', 'username' => 'admin'], attributes: ['_route' => 'api.oauth.token']);

        $this->subscriber->onLoginResponse(new ResponseEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new Response('', 200)));

        $record = $this->logs->getRecords()[0];
        static::assertSame('user:login', $record->message);
        static::assertSame(['actorType' => 'user', 'loginUsername' => 'admin', 'statusCode' => 200], $record->context);
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

    public function testMalformedLoginParametersDoNotBreakTheErrorResponse(): void
    {
        $request = new Request(request: ['grant_type' => ['password'], 'client_id' => ['administration'], 'username' => ['secret']], attributes: ['_route' => 'api.oauth.token']);
        $this->subscriber->onLoginResponse(new ResponseEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new Response('', 400)));
        static::assertCount(0, $this->logs->getRecords());
    }

    #[DataProvider('loginResponses')]
    public function testRecordsOnlyPasswordLoginOutcomes(int $status, string $grant, ?string $action, int $requestType = HttpKernelInterface::MAIN_REQUEST): void
    {
        $request = new Request(request: ['grant_type' => $grant, 'client_id' => 'administration', 'username' => 'admin', 'password' => 'secret', 'refresh_token' => 'secret'], attributes: ['_route' => 'api.oauth.token']);
        $this->subscriber->onLoginResponse(new ResponseEvent(static::createStub(HttpKernelInterface::class), $request, $requestType, new Response('{"access_token":"secret"}', $status)));

        if ($action === null) {
            static::assertCount(0, $this->logs->getRecords());

            return;
        }
        $record = $this->logs->getRecords()[0];
        static::assertSame($action, $record->message);
        static::assertSame(['actorType' => $status === 200 ? 'user' : 'anonymous', 'loginUsername' => 'admin', 'statusCode' => $status], $record->context);
    }

    public static function loginResponses(): iterable
    {
        yield 'successful login' => [200, 'password', 'user:login'];
        yield 'rejected credentials' => [401, 'password', 'user:login_failed'];
        yield 'rate limited login' => [429, 'password', 'user:login_failed'];
        yield 'refresh is not login' => [200, 'refresh_token', null];
        yield 'server failure is not credential rejection' => [500, 'password', null];
        yield 'ignore subrequests' => [200, 'password', null, HttpKernelInterface::SUB_REQUEST];
    }
}
