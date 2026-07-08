<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CompressionMethodChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public readonly bool $cacheCompressionEnabled;

    public readonly string $cacheCompressionMethod;

    public function __construct(
        #[Autowire(param: 'kernel.shopware_version')]
        public readonly string $shopwareVersion,
        #[Autowire(param: 'shopware.cache.cache_compression')]
        public readonly ?bool $deprecatedCacheCompressionEnabled,
        #[Autowire(param: 'shopware.cache.cache_compression_method')]
        public readonly ?string $deprecatedCacheCompressionMethod,
        #[Autowire(param: 'shopware.cache.compress')]
        ?bool $cacheCompressionEnabled,
        #[Autowire(param: 'shopware.cache.compression_method')]
        ?string $cacheCompressionMethod,
        #[Autowire(param: 'shopware.cart.compress')]
        public readonly bool $cartCompressionEnabled,
        #[Autowire(param: 'shopware.cart.compression_method')]
        public readonly string $cartCompressionMethod,
    ) {
        if ($cacheCompressionEnabled === null) {
            \assert($this->deprecatedCacheCompressionEnabled !== null);

            $this->cacheCompressionEnabled = $this->deprecatedCacheCompressionEnabled;
        } else {
            $this->cacheCompressionEnabled = $cacheCompressionEnabled;
        }

        if ($cacheCompressionMethod === null) {
            \assert($this->deprecatedCacheCompressionMethod !== null);

            $this->cacheCompressionMethod = $this->deprecatedCacheCompressionMethod;
        } else {
            $this->cacheCompressionMethod = $cacheCompressionMethod;
        }
    }

    public function collect(HealthCollection $collection): void
    {
        if (\version_compare('6.6.4.0', $this->shopwareVersion, '>')) {
            return;
        }

        $this->checkCompression($collection, 'Cache', $this->cacheCompressionEnabled, $this->cacheCompressionMethod);
        $this->checkCompression($collection, 'Cart', $this->cartCompressionEnabled, $this->cartCompressionMethod);
    }

    private function checkCompression(HealthCollection $collection, string $functionality, bool $enabled, string $method): void
    {
        if (!$enabled) {
            return;
        }

        if ($method === 'gzip' && \version_compare($this->shopwareVersion, '6.7.1.0', '<')) {
            $collection->add(
                SettingsResult::info(
                    strtolower($functionality) . '-compression-method',
                    $functionality . ' compression method',
                    'gzip',
                    'zstd',
                ),
            );

            return;
        }

        if ($method === 'zstd' && !\extension_loaded('zstd')) {
            $collection->add(
                SettingsResult::error(
                    strtolower($functionality) . '-compression-method-extension-zstd',
                    'PHP extension zstd for ' . $functionality . ' compression method',
                    'disabled',
                    'enabled',
                ),
            );
        }
    }
}
