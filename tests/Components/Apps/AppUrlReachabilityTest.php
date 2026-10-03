<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Apps;

use Frosh\Tools\Components\Apps\AppUrlReachability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[CoversClass(AppUrlReachability::class)]
#[Package('framework')]
class AppUrlReachabilityTest extends TestCase
{
    public function testProofIdentifiesShopAcrossWorkersWithoutSharedCache(): void
    {
        $challenge = str_repeat('a', 64);
        $first = new AppUrlReachability(new MockHttpClient(), new ArrayAdapter(), 'shop-secret');
        $second = new AppUrlReachability(new MockHttpClient(), new ArrayAdapter(), 'shop-secret');
        $otherShop = new AppUrlReachability(new MockHttpClient(), new ArrayAdapter(), 'different-secret');

        static::assertSame($first->createProof($challenge), $second->createProof($challenge));
        static::assertNotSame($first->createProof($challenge), $otherShop->createProof($challenge));
        static::assertNotSame($first->createProof($challenge), $first->createProof(str_repeat('b', 64)));
        static::assertNull($first->createProof(''));
        static::assertNull($first->createProof(str_repeat('z', 64)));
    }

    public function testOnlyCallsPublicCloudflareWorkerWithFreshChallengeAndCachesItsResult(): void
    {
        $challenges = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$challenges): MockResponse {
            static::assertSame('POST', $method);
            static::assertSame('https://app-url-check.fos.gg/', $url);
            $payload = json_decode($options['body'], true, 512, \JSON_THROW_ON_ERROR);
            static::assertSame('https://shop.example.com/subdir', $payload['appUrl']);
            static::assertContains('Authorization: Bearer FroshTools', $options['headers']);
            static::assertSame(0, $options['max_redirects']);
            static::assertSame(hash_hmac('sha256', 'frosh-tools-app-url-reachability:' . $payload['challenge'], 'shop-secret'), $payload['expectedProof']);
            static::assertArrayNotHasKey('secret', $payload);
            $challenges[] = $payload['challenge'];

            return new MockResponse(json_encode(['status' => 'pass', 'proof' => $payload['expectedProof']], \JSON_THROW_ON_ERROR));
        });
        $service = new AppUrlReachability($client, new ArrayAdapter(), 'shop-secret');
        static::assertSame('unknown', $service->getCurrentState('https://shop.example.com/subdir')['status']);
        $result = $service->check('https://shop.example.com/subdir/');
        static::assertSame('pass', $result['status']);
        static::assertNotNull($result['checkedAt']);
        static::assertSame($result, $service->getCurrentState('https://shop.example.com/subdir'));
        static::assertSame('unknown', $service->getCurrentState('https://other-shop.example.com')['status']);
        $service->check('https://shop.example.com/subdir');
        static::assertNotSame($challenges[0], $challenges[1]);
    }

    public function testWorkerConnectionFailureDoesNotMarkShopUnreachable(): void
    {
        $client = new MockHttpClient(static function (): never { throw new TransportException('Could not connect'); });
        $service = new AppUrlReachability($client, new ArrayAdapter(), 'shop-secret');
        $result = $service->check('https://shop.example.com');
        static::assertSame('unknown', $result['status']);
        static::assertNull($result['checkedAt']);
    }

    public function testWorkerMalformedJsonDoesNotMarkShopUnreachable(): void
    {
        $service = new AppUrlReachability(new MockHttpClient(new MockResponse('<html>gateway error</html>')), new ArrayAdapter(), 'shop-secret');
        static::assertSame('unknown', $service->check('https://shop.example.com')['status']);
    }

    #[DataProvider('invalidUrls')]
    public function testInvalidAppUrlIsRejectedWithoutCallingWorker(string $url): void
    {
        $client = new MockHttpClient(static function (): never { static::fail('Invalid APP_URL must not cause a request'); });
        $service = new AppUrlReachability($client, new ArrayAdapter(), 'shop-secret');
        static::assertSame('hard_fail', $service->check($url)['status']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUrls(): iterable
    {
        yield 'missing APP_URL' => [''];
        yield 'malformed APP_URL' => ['invalid'];
        yield 'insecure APP_URL' => ['http://shop.example.com'];
    }

    /**
     * @param array<string, mixed> $response
     */
    #[DataProvider('workerResponses')]
    public function testValidatesWorkerResponse(array $response, int $httpStatus, string $expectedStatus): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode($response, \JSON_THROW_ON_ERROR), ['http_code' => $httpStatus]));
        $service = new AppUrlReachability($client, new ArrayAdapter(), 'shop-secret');
        static::assertSame($expectedStatus, $service->check('https://shop.example.com')['status']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, int, string}>
     */
    public static function workerResponses(): iterable
    {
        yield 'worker unauthorized' => [[], 401, 'unknown'];
        yield 'worker unavailable' => [[], 503, 'unknown'];
        yield 'invalid worker response' => [[], 200, 'unknown'];
        yield 'missing proof' => [['status' => 'pass'], 200, 'hard_fail'];
        yield 'wrong proof' => [['status' => 'pass', 'proof' => 'wrong'], 200, 'hard_fail'];
        yield 'unreachable shop' => [['status' => 'soft_fail', 'info' => 'timeout'], 200, 'soft_fail'];
        yield 'wrong shop' => [['status' => 'hard_fail', 'info' => 'invalid proof'], 200, 'hard_fail'];
    }
}
