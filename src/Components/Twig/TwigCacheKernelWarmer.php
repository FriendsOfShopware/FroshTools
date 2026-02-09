<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class TwigCacheKernelWarmer implements CacheWarmerInterface
{
    public function __construct(
        private readonly TwigCacheWarmer $twigCacheWarmer,
        #[Autowire('%frosh_tools.twig_cache_warmer.enabled%')]
        private bool $enabled
    ) {
    }

    /**
     * @return list<string>
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if (!$this->enabled) {
            return [];
        }

        return $this->twigCacheWarmer->warmUp($cacheDir);
    }

    public function isOptional(): bool
    {
        return false;
    }
}
