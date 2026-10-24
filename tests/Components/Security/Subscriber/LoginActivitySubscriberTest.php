<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Subscriber;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\Subscriber\LoginActivitySubscriber;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/** @internal */
#[Package('framework')]
#[CoversClass(LoginActivitySubscriber::class)]
class LoginActivitySubscriberTest extends TestCase
{
    private TestHandler $logs;

    private LoginActivitySubscriber $subscriber;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->logs = new TestHandler();
        $this->connection = static::createStub(Connection::class);
        $this->subscriber = new LoginActivitySubscriber(new Logger('system_activity', [$this->logs]), $this->connection);
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
