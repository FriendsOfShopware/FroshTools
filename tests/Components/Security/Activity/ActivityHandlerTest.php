<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Activity;

use Frosh\Tools\Components\Security\Activity\ActivityHandler;
use Frosh\Tools\Components\Security\Activity\ActivityStore;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ActivityHandler::class)]
class ActivityHandlerTest extends TestCase
{
    public function testOnlyAllowlistedMetadataIsPersisted(): void
    {
        $now = new \DateTimeImmutable();
        $store = $this->createMock(ActivityStore::class);
        $store->expects($this->once())->method('append')->with('user:update', 200, [
            'userId' => 'actor', 'changedFields' => ['password'],
        ], $now);
        $handler = new ActivityHandler($store, new NullLogger(), new RequestStack());
        $handler->handle(new LogRecord($now, 'system_activity', Level::Info, 'user:update', [
            'userId' => 'actor', 'password' => 'secret', 'token' => 'secret', 'clientIp' => 'spoofed',
            'request' => ['password' => 'secret'], 'changedFields' => ['password', 'not-an-allowed-field'],
        ], ['authorization' => 'secret']));
    }

    /**
     * @param array<string, string> $server
     * @param list<string> $trustedProxies
     */
    #[DataProvider('clientAddresses')]
    public function testCapturesRequestAddressUsingTrustedProxyConfiguration(array $server, array $trustedProxies, ?string $expectedIp): void
    {
        $previousProxies = Request::getTrustedProxies();
        $previousHeaders = Request::getTrustedHeaderSet();
        Request::setTrustedProxies($trustedProxies, Request::HEADER_X_FORWARDED_FOR | Request::HEADER_FORWARDED);
        try {
            $requests = new RequestStack();
            $requests->push(new Request(server: $server));
            // A subrequest must not replace the originating HTTP client's address.
            $requests->push(new Request(server: ['REMOTE_ADDR' => '127.0.0.1']));
            $now = new \DateTimeImmutable();
            $store = $this->createMock(ActivityStore::class);
            $store->expects($this->once())->method('append')->with('user:login_failed', 300, $expectedIp === null ? [] : ['clientIp' => $expectedIp], $now);
            $handler = new ActivityHandler($store, new NullLogger(), $requests);
            $handler->handle(new LogRecord($now, 'system_activity', Level::Warning, 'user:login_failed', ['clientIp' => 'spoofed']));
        } finally {
            Request::setTrustedProxies($previousProxies, $previousHeaders);
        }
    }

    public static function clientAddresses(): iterable
    {
        yield 'direct IPv4 request' => [['REMOTE_ADDR' => '192.0.2.10'], [], '192.0.2.10'];
        yield 'direct IPv6 request' => [['REMOTE_ADDR' => '2001:db8::1'], [], '2001:db8::1'];
        yield 'trusted proxy forwards client address' => [['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '192.0.2.20'], ['10.0.0.1'], '192.0.2.20'];
        yield 'untrusted forwarded header is ignored' => [['REMOTE_ADDR' => '192.0.2.10', 'HTTP_X_FORWARDED_FOR' => '192.0.2.20'], [], '192.0.2.10'];
        yield 'missing address is omitted' => [[], [], null];
        yield 'conflicting trusted headers preserve the event without an address' => [['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '192.0.2.20', 'HTTP_FORWARDED' => 'for=192.0.2.30'], ['10.0.0.1'], null];
    }

    public function testBackgroundActivityDoesNotReuseAPreviousRequestAddress(): void
    {
        $requests = new RequestStack();
        $requests->push(new Request(server: ['REMOTE_ADDR' => '192.0.2.10']));
        $requests->pop();
        $store = $this->createMock(ActivityStore::class);
        $store->expects($this->once())->method('append')->with('plugin:install', 200, ['actorType' => 'system'], $this->isInstanceOf(\DateTimeImmutable::class));
        $handler = new ActivityHandler($store, new NullLogger(), $requests);
        $handler->handle(new LogRecord(new \DateTimeImmutable(), 'system_activity', Level::Info, 'plugin:install', ['actorType' => 'system']));
    }

    public function testIgnoresOtherChannelsAndFreeTextMessages(): void
    {
        $store = $this->createMock(ActivityStore::class);
        $store->expects($this->never())->method('append');
        $handler = new ActivityHandler($store, new NullLogger(), new RequestStack());
        $handler->handle(new LogRecord(new \DateTimeImmutable(), 'app', Level::Info, 'user:create'));
        $handler->handle(new LogRecord(new \DateTimeImmutable(), 'system_activity', Level::Info, 'credentials: secret'));
    }

    public function testStorageFailureIsReportedWithoutInterruptingAuthenticationOrLeakingData(): void
    {
        $store = $this->createStub(ActivityStore::class);
        $store->method('append')->willThrowException(new \RuntimeException('SQL contains secret'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('Could not persist FroshTools security activity.', ['exceptionClass' => \RuntimeException::class]);
        $handler = new ActivityHandler($store, $logger, new RequestStack());
        $handler->handle(new LogRecord(new \DateTimeImmutable(), 'system_activity', Level::Info, 'user:create'));
    }
}
