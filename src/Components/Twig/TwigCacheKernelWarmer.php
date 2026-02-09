<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Twig;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class TwigCacheKernelWarmer implements CacheWarmerInterface
{
    public function __construct(
        private readonly TwigCacheWarmer $twigCacheWarmer
    ) {
    }

    /**
     * @return list<string>
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if (!EnvironmentHelper::getVariable('FROSH_TOOLS_WARMUP_TWIG', false)) {
            return [];
        }

        try {
            return $this->twigCacheWarmer->warmUp($cacheDir);
        } catch (\Throwable $e) {
            if (\defined('STDERR')) {
                fwrite(STDERR, 'Twig cache warming failed: ' . $e->getMessage() . PHP_EOL);
            }

            return [];
        }
    }

    public function isOptional(): bool
    {
        return false;
    }
}
