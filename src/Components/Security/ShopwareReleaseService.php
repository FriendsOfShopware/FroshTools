<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ShopwareReleaseService
{
    private const RELEASES_URL = 'https://raw.githubusercontent.com/shopware/shopware/trunk/releases.json';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cacheObject,
        #[Autowire(param: 'kernel.shopware_version')]
        private readonly string $shopwareVersion,
    ) {
    }

    /**
     * @return array{version?: string, release_date?: string, extended_eol?: string|false, security_eol?: string, extended_eol_version?: string}
     */
    public function getReleasesSupport(): array
    {
        $cacheKey = \sprintf('shopware-releases-support-%s', $this->shopwareVersion);

        return $this->cacheObject->get($cacheKey, function (ItemInterface $cacheItem) {
            try {
                $releasesJson = $this->httpClient->request('GET', self::RELEASES_URL)->getContent();
            } catch (\Throwable) {
                throw new \RuntimeException('Could not fetch releases.json');
            }

            $data = \json_decode(trim($releasesJson), true, 512, \JSON_THROW_ON_ERROR);

            if (!\is_array($data)) {
                throw new \RuntimeException('Could not read releases.json');
            }

            $cacheItem->expiresAfter(3600 * 24 * 14);

            $result = [];

            foreach ($data as $entry) {
                if (!\is_array($entry) || !isset($entry['version']) || $entry['version'] === '') {
                    continue;
                }

                if (isset($entry['extended_eol']) && $entry['extended_eol'] !== false && $entry['extended_eol'] !== ''
                    && version_compare($entry['version'], $result['extended_eol_version'] ?? '', '>')) {
                    $result['extended_eol_version'] = $entry['version'];
                }

                if (version_compare($entry['version'], $result['version'] ?? '', '>')
                    && version_compare($entry['version'], $this->shopwareVersion, '<=')) {
                    $result = [...$result, ...$entry];
                }
            }

            return $result;
        });
    }

    public function getShopwareVersion(): string
    {
        return $this->shopwareVersion;
    }
}
