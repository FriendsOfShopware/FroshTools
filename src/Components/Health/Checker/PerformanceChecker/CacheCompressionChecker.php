<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CacheCompressionChecker extends AbstractCompressionChecker
{
    public function __construct(
        #[Autowire('%kernel.shopware_version%')]
        string $shopwareVersion,
        #[Autowire('Cache')]
        string $functionality,
        #[Autowire('%shopware.cache.cache_compression%')]
        bool $enabled,
        #[Autowire('%shopware.cache.cache_compression_method%')]
        string $method,
    ) {
        parent::__construct($shopwareVersion, $functionality, $enabled, $method);
    }
}
