<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Resolves end-of-life information for a product release cycle from endoflife.date.
 *
 * No baked-in fallback table is used: when the API is unreachable the cycle is reported
 * as unknown so the calling checker can surface that explicitly.
 */
class EndOfLifeService
{
    private const API_URL = 'https://endoflife.date/api/%s.json';
    private const CACHE_TTL_SECONDS = 3600 * 24;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cacheObject,
    ) {}

    /**
     * Resolve the EOL information for the cycle matching the given version (major.minor).
     *
     * @return array{cycle: string, eol: \DateTimeImmutable|null, eolUnknown: bool, supportEnded: bool, latest: string|null}|null
     *                                                                                                                            null when the product or cycle is not known / the API was unreachable
     */
    public function getCycle(string $product, string $version): ?array
    {
        $cycle = $this->extractCycle($version);

        $cycles = $this->fetchProduct($product);
        if ($cycles === null) {
            return null;
        }

        foreach ($cycles as $entry) {
            if (!isset($entry['cycle'])) {
                continue;
            }

            if ((string) $entry['cycle'] !== $cycle) {
                continue;
            }

            return $this->normalizeEntry($entry);
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function fetchProduct(string $product): ?array
    {
        $cacheKey = \sprintf('frosh-tools-eol-%s', $product);

        return $this->cacheObject->get($cacheKey, function (ItemInterface $cacheItem) use ($product): ?array {
            $cacheItem->expiresAfter(self::CACHE_TTL_SECONDS);

            try {
                $content = $this->httpClient->request('GET', \sprintf(self::API_URL, $product), [
                    'timeout' => 15,
                ])->getContent();
            } catch (\Throwable) {
                // Do not cache failures for a full day; retry sooner.
                $cacheItem->expiresAfter(300);

                return null;
            }

            try {
                $data = \json_decode(trim($content), true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $cacheItem->expiresAfter(300);

                return null;
            }

            if (!\is_array($data)) {
                return null;
            }

            $cycles = [];
            foreach ($data as $entry) {
                if (\is_array($entry)) {
                    $cycles[] = $entry;
                }
            }

            return $cycles;
        });
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array{cycle: string, eol: \DateTimeImmutable|null, eolUnknown: bool, supportEnded: bool, latest: string|null}
     */
    private function normalizeEntry(array $entry): array
    {
        $now = new \DateTimeImmutable();

        $eol = $entry['eol'] ?? null;
        $eolDate = null;
        $eolUnknown = false;

        if ($eol === true) {
            // Already end-of-life with no concrete date.
            $eolDate = $now->modify('-1 day');
        } elseif (\is_string($eol) && $eol !== '') {
            try {
                $eolDate = new \DateTimeImmutable($eol);
            } catch (\Throwable) {
                $eolUnknown = true;
            }
        } elseif ($eol === false) {
            $eolDate = null;
        } else {
            $eolUnknown = true;
        }

        $support = $entry['support'] ?? null;
        $supportEnded = false;
        if ($support === true) {
            $supportEnded = true;
        } elseif (\is_string($support) && $support !== '') {
            try {
                $supportEnded = new \DateTimeImmutable($support) < $now;
            } catch (\Throwable) {
                $supportEnded = false;
            }
        }

        return [
            'cycle' => (string) $entry['cycle'],
            'eol' => $eolDate,
            'eolUnknown' => $eolUnknown,
            'supportEnded' => $supportEnded,
            'latest' => isset($entry['latest']) ? (string) $entry['latest'] : null,
        ];
    }

    private function extractCycle(string $version): string
    {
        if (preg_match('/^(\d+)\.(\d+)/', $version, $matches) === 1) {
            return $matches[1] . '.' . $matches[2];
        }

        if (preg_match('/^(\d+)/', $version, $matches) === 1) {
            return $matches[1];
        }

        return $version;
    }
}
