<?php declare(strict_types=1);

namespace Frosh\Tools;

use Frosh\Tools\Components\Messenger\TaskLoggingMiddlewareCompilerPass;
use Frosh\Tools\DependencyInjection\CacheCompilerPass;
use Frosh\Tools\DependencyInjection\DisableElasticsearchCompilerPass;
use Frosh\Tools\DependencyInjection\SymfonyConfigCompilerPass;
use Frosh\Tools\DependencyInjection\FroshToolsExtension;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FroshTools extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new CacheCompilerPass());
        $container->addCompilerPass(new TaskLoggingMiddlewareCompilerPass());
        $container->addCompilerPass(new SymfonyConfigCompilerPass());
        $container->addCompilerPass(new DisableElasticsearchCompilerPass());
    }

    public function createContainerExtension(): FroshToolsExtension
    {
        return new FroshToolsExtension();
    }
}
