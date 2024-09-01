<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CartCompressionChecker extends AbstractCompressionChecker
{
    public function __construct(
        #[Autowire('%kernel.shopware_version%')]
        string $shopwareVersion,
        #[Autowire('Cart')]
        string $functionality,
        #[Autowire('%shopware.cart.compress%')]
        bool $enabled,
        #[Autowire('%shopware.cart.compression_method%')]
        string $method,
    ) {
        parent::__construct($shopwareVersion, $functionality, $enabled, $method);
    }
}
