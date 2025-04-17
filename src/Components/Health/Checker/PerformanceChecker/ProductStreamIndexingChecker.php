<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ProductStreamIndexingChecker implements PerformanceCheckerInterface, CheckerInterface
{
    public function __construct(
        #[Autowire(param: 'shopware.product_stream.indexing')]
        private readonly bool $productStreamIndexingEnabled,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        if ($this->productStreamIndexingEnabled) {
            $collection->add(
                SettingsResult::info(
                    'product-stream-indexing',
                    'Product Stream Indexing',
                    'enabled',
                    'disabled',
                    'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks.html#disable-product-stream-indexer',
                ),
            );
        }
    }
}
