<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Telemetry;

use Frosh\Tools\Components\Telemetry\PostHogClient;
use Frosh\Tools\Components\Telemetry\TelemetryCollector;
use Frosh\Tools\Components\Telemetry\TelemetryService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(TelemetryService::class)]
#[CoversClass(PostHogClient::class)]
#[CoversClass(TelemetryCollector::class)]
class TelemetryServiceTest extends TestCase
{
    public function testDoesNotSendWhenNotOptedIn(): void
    {
        $requests = 0;
        $service = $this->createService(enabled: false, httpClient: new MockHttpClient(function () use (&$requests): ResponseInterface {
            ++$requests;

            return new MockResponse('{"status":"Ok"}');
        }));

        static::assertFalse($service->send());
        static::assertSame(0, $requests);
    }

    public function testDoesNotSendWithPlaceholderApiKey(): void
    {
        $requests = 0;
        $service = $this->createService(enabled: true, apiKey: PostHogClient::DEFAULT_API_KEY, httpClient: new MockHttpClient(function () use (&$requests): ResponseInterface {
            ++$requests;

            return new MockResponse('{"status":"Ok"}');
        }));

        static::assertFalse($service->send());
        static::assertSame(0, $requests);
    }

    public function testSendsAnonymousEventToPostHogEuAndPersistsInstanceId(): void
    {
        $bodies = [];
        $urls = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$bodies, &$urls): ResponseInterface {
            $urls[] = $url;
            $bodies[] = json_decode((string) ($options['body'] ?? ''), true);

            return new MockResponse('{"status":"Ok"}', ['http_code' => 200]);
        });

        $stored = [];
        $service = $this->createService(enabled: true, httpClient: $httpClient, stored: $stored);

        static::assertTrue($service->send());
        static::assertTrue($service->send());

        static::assertSame(['https://eu.i.posthog.com/capture/', 'https://eu.i.posthog.com/capture/'], $urls);
        static::assertCount(2, $bodies);
        static::assertSame('phc_test', $bodies[0]['api_key']);
        static::assertSame('frosh_tools_telemetry', $bodies[0]['event']);
        static::assertNotSame('', (string) $bodies[0]['distinct_id']);
        // The generated instance ID is persisted and reused for the next send.
        static::assertSame($bodies[0]['distinct_id'], $bodies[1]['distinct_id']);
        static::assertSame([(string) $bodies[0]['distinct_id']], $stored);

        $properties = $bodies[0]['properties'];
        static::assertFalse($properties['$process_person_profile']);
        static::assertTrue($properties['$geoip_disable']);
        static::assertSame(TelemetryCollector::SCHEMA_VERSION, $properties['schema_version']);
        static::assertSame('6.7.1.0', $properties['shopware.version']);
        static::assertArrayNotHasKey('$ip', $properties);
        static::assertArrayNotHasKey('shop.url', $properties);
    }

    public function testSendReturnsFalseOnTransportError(): void
    {
        $service = $this->createService(enabled: true, httpClient: new MockHttpClient(fn (): ResponseInterface => throw new TransportException('network down')));

        static::assertFalse($service->send());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function bucketProvider(): iterable
    {
        yield 'zero' => [0, '0'];
        yield 'one' => [1, '1-10'];
        yield 'ten' => [10, '1-10'];
        yield 'eleven' => [11, '11-100'];
        yield 'hundred' => [100, '11-100'];
        yield 'thousand' => [1000, '101-1000'];
        yield 'ten thousand' => [10000, '1001-10000'];
        yield 'hundred thousand' => [100000, '10001-100000'];
        yield 'above' => [100001, '100001+'];
    }

    #[DataProvider('bucketProvider')]
    public function testBucket(int $count, string $expected): void
    {
        static::assertSame($expected, TelemetryCollector::bucket($count));
    }

    public function testMajorMinor(): void
    {
        static::assertSame('6.7', TelemetryCollector::majorMinor('6.7.1.0'));
        static::assertSame('10.11', TelemetryCollector::majorMinor('10.11.8-MariaDB-1:10.11.8+maria'));
        static::assertSame('10.6', TelemetryCollector::majorMinor('5.5.5-10.6.18-MariaDB'));
        static::assertSame('8.0', TelemetryCollector::majorMinor('8.0.36'));
        static::assertNull(TelemetryCollector::majorMinor('6.x-dev'));
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function mailTransportProvider(): iterable
    {
        yield 'smtp with credentials' => ['smtp://user:secret@mail.example.com:587', 'smtp'];
        yield 'smtps' => ['smtps://user:secret@mail.example.com:465', 'smtps'];
        yield 'sendmail' => ['sendmail://default', 'sendmail'];
        yield 'null' => ['null://null', 'null'];
        yield 'failover wrapper' => ['failover(smtp://mail.example.com sendmail://default)', 'failover'];
        yield 'known provider' => ['ses://key:secret@default?region=eu-central-1', 'ses'];
        yield 'unknown scheme fails closed' => ['secret-shop-transport://user:secret@host', 'other'];
        yield 'empty' => ['', 'unknown'];
        yield 'no dsn' => [null, 'unknown'];
    }

    #[DataProvider('mailTransportProvider')]
    public function testMailTransportOnlyExposesAllowlistedScheme(?string $dsn, string $expected): void
    {
        $transport = TelemetryCollector::mailTransport($dsn);

        static::assertSame($expected, $transport);
        static::assertStringNotContainsString('secret', $transport);
        static::assertStringNotContainsString('example.com', $transport);
    }

    public function testHttpCacheType(): void
    {
        static::assertSame('fastly', TelemetryCollector::httpCacheType(true, true));
        static::assertSame('fastly', TelemetryCollector::httpCacheType(false, true));
        static::assertSame('varnish', TelemetryCollector::httpCacheType(true, false));
        static::assertSame('builtin', TelemetryCollector::httpCacheType(false, false));
    }

    public function testSessionHandlerTypeUnwrapsWrappers(): void
    {
        $nativeFile = new \Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler();

        static::assertSame('file', TelemetryCollector::sessionHandlerType($nativeFile));
        static::assertSame('file', TelemetryCollector::sessionHandlerType(
            new \Symfony\Component\HttpFoundation\Session\Storage\Handler\StrictSessionHandler($nativeFile),
        ));
        static::assertSame('unknown', TelemetryCollector::sessionHandlerType(null));
    }

    public function testSetEnabledWritesThePluginConfiguration(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects(static::once())
            ->method('set')
            ->with(TelemetryService::CONFIG_ENABLED, true);

        $service = new TelemetryService(
            $configService,
            $this->createStub(TelemetryCollector::class),
            new PostHogClient(new MockHttpClient(), 'phc_test'),
            new NullLogger(),
        );

        $service->setEnabled(true);
    }

    public function testGetInstanceIdDoesNotCreateAnId(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('getString')->willReturn('');
        $configService->expects(static::never())->method('set');

        $service = new TelemetryService(
            $configService,
            $this->createStub(TelemetryCollector::class),
            new PostHogClient(new MockHttpClient(), 'phc_test'),
            new NullLogger(),
        );

        static::assertNull($service->getInstanceId());
    }

    public function testResetInstanceIdDeletesTheStoredId(): void
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects(static::once())
            ->method('delete')
            ->with(TelemetryService::CONFIG_INSTANCE_ID);

        $service = new TelemetryService(
            $configService,
            $this->createStub(TelemetryCollector::class),
            new PostHogClient(new MockHttpClient(), 'phc_test'),
            new NullLogger(),
        );

        $service->resetInstanceId();
    }

    /**
     * @param list<string> $stored
     */
    private function createService(bool $enabled, MockHttpClient $httpClient, string $apiKey = 'phc_test', array &$stored = []): TelemetryService
    {
        $configService = $this->createMock(SystemConfigService::class);
        $configService->method('getBool')->willReturn($enabled);
        $configService->method('getString')->willReturnCallback(function () use (&$stored): string {
            return $stored[0] ?? '';
        });
        $configService->method('set')->willReturnCallback(static function (string $key, mixed $value) use (&$stored): void {
            $stored[] = (string) $value;
        });

        $collector = $this->createStub(TelemetryCollector::class);
        $collector->method('collect')->willReturn([
            'schema_version' => TelemetryCollector::SCHEMA_VERSION,
            'shopware.version' => '6.7.1.0',
        ]);

        return new TelemetryService(
            $configService,
            $collector,
            new PostHogClient($httpClient, $apiKey),
            new NullLogger(),
        );
    }
}
