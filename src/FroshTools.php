<?php

declare(strict_types=1);

namespace Frosh\Tools;

use Frosh\Tools\DependencyInjection\CacheCompilerPass;
use Frosh\Tools\DependencyInjection\DisableElasticsearchCompilerPass;
use Frosh\Tools\DependencyInjection\FroshToolsExtension;
use Frosh\Tools\DependencyInjection\SymfonyConfigCompilerPass;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FroshTools extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new CacheCompilerPass());
        $container->addCompilerPass(new SymfonyConfigCompilerPass());
        $container->addCompilerPass(new DisableElasticsearchCompilerPass());
    }

    public static function formatSize(float $size): string
    {
        if ($size <= 0) {
            return '0';
        }

        $base = log($size) / log(1024);
        $suffix = ['', 'k', 'M', 'G', 'T'][(int) floor($base)];

        return round(1024 ** ($base - floor($base)), 2) . $suffix;
    }

    protected function createContainerExtension(): FroshToolsExtension
    {
        return new FroshToolsExtension();
    }
}
