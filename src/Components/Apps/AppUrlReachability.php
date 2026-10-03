<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Apps;

use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
final class AppUrlReachability
{
    private const WORKER_URL = 'https://app-url-check.fos.gg/';
    private const RESULT_CACHE_KEY = 'frosh_tools_app_url_reachability_';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
        #[Autowire(param: 'kernel.secret')]
        private readonly string $secret,
    ) {
    }

    /**
     * @return array{status: string, checkedAt: string|null, info: string|null, detailed: bool}
     */
    public function getCurrentState(string $appUrl): array
    {
        $item = $this->cache->getItem($this->cacheKey($appUrl));
        $result = $item->get();
        if ($item->isHit() && \is_array($result) && \is_string($result['status'] ?? null)) {
            return $this->result(
                $result['status'],
                \is_string($result['checkedAt'] ?? null) ? $result['checkedAt'] : null,
                \is_string($result['info'] ?? null) ? $result['info'] : null,
            );
        }

        return $this->result('unknown', null, null);
    }

    public function createProof(string $challenge): ?string
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $challenge) !== 1) {
            return null;
        }

        return hash_hmac('sha256', 'frosh-tools-app-url-reachability:' . $challenge, $this->secret);
    }

    /**
     * @return array{status: string, checkedAt: string|null, info: string|null, detailed: bool}
     */
    public function check(string $appUrl): array
    {
        $result = $this->verify(rtrim($appUrl, '/'));
        $item = $this->cache->getItem($this->cacheKey($appUrl));
        $item->set($result);
        $item->expiresAfter(86400);
        $this->cache->save($item);

        return $result;
    }

    /**
     * @return array{status: string, checkedAt: string|null, info: string|null, detailed: bool}
     */
    private function verify(string $appUrl): array
    {
        $checkedAt = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        if ($appUrl === '') {
            return $this->result('hard_fail', $checkedAt, 'APP_URL is not configured');
        }

        if (filter_var($appUrl, \FILTER_VALIDATE_URL) === false || parse_url($appUrl, \PHP_URL_SCHEME) !== 'https') {
            return $this->result('hard_fail', $checkedAt, 'APP_URL must be a valid HTTPS URL');
        }

        $challenge = bin2hex(random_bytes(32));
        $expectedProof = $this->createProof($challenge);

        try {
            $response = $this->httpClient->request('POST', self::WORKER_URL, [
                'json' => ['appUrl' => $appUrl, 'challenge' => $challenge, 'expectedProof' => $expectedProof],
                'timeout' => 10,
                'max_redirects' => 0,
                // This is the public protocol marker used by the shared Worker.
                'headers' => ['Authorization' => 'Bearer FroshTools', 'Accept' => 'application/json'],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                return $this->result('unknown', null, \sprintf('External reachability Worker returned HTTP "%d"', $statusCode));
            }

            $data = $response->toArray(false);
            $status = $data['status'] ?? null;
            if (!\is_string($status) || !\in_array($status, ['pass', 'hard_fail', 'soft_fail'], true)) {
                return $this->result('unknown', null, 'Invalid response from the external reachability Worker');
            }

            if ($status === 'pass') {
                $proof = $data['proof'] ?? null;
                if (!\is_string($proof) || $expectedProof === null || !hash_equals($expectedProof, $proof)) {
                    return $this->result('hard_fail', $checkedAt, 'APP_URL did not return a valid reachability proof for this shop');
                }

                return $this->result('pass', $checkedAt, null);
            }

            return $this->result($status, $checkedAt, \is_string($data['info'] ?? null) ? $data['info'] : 'External reachability verification failed');
        } catch (TransportExceptionInterface) {
            return $this->result('unknown', null, 'Could not connect to the external reachability Worker');
        } catch (\Throwable) {
            return $this->result('unknown', null, 'Invalid response from the external reachability Worker');
        }
    }

    private function cacheKey(string $appUrl): string
    {
        return self::RESULT_CACHE_KEY . hash('sha256', rtrim($appUrl, '/'));
    }

    /**
     * @return array{status: string, checkedAt: string|null, info: string|null, detailed: bool}
     */
    private function result(string $status, ?string $checkedAt, ?string $info): array
    {
        return ['status' => $status, 'checkedAt' => $checkedAt, 'info' => $info, 'detailed' => true];
    }
}
