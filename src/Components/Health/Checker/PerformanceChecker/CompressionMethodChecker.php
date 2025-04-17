<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CompressionMethodChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public const DOCUMENTATION_URL = 'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#using-zstd-instead-of-gzip-for-compression';

    public function __construct(
        #[Autowire(param: 'kernel.shopware_version')]
        public readonly string $shopwareVersion,
        #[Autowire(param: 'shopware.cache.cache_compression')]
        public readonly bool $cacheCompressionEnabled,
        #[Autowire(param: 'shopware.cache.cache_compression_method')]
        public readonly string $cacheCompressionMethod,
        #[Autowire(param: 'shopware.cart.compress')]
        public readonly bool $cartCompressionEnabled,
        #[Autowire(param: 'shopware.cart.compression_method')]
        public readonly string $cartCompressionMethod,
    ) {
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

        if ($method === 'gzip') {
            $collection->add(
                SettingsResult::info(
                    strtolower($functionality) . '-compression-method',
                    $functionality . ' compression method',
                    'gzip',
                    'zstd',
                    self::DOCUMENTATION_URL,
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
                    self::DOCUMENTATION_URL,
                ),
            );
        }
    }
}
