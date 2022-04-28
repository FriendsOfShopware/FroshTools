<?php declare(strict_types=1);

namespace Frosh\Tools;

use Frosh\Tools\Components\Messenger\TaskLoggingMiddlewareCompilerPass;
use Frosh\Tools\DependencyInjection\CacheCompilerPass;
use Frosh\Tools\DependencyInjection\FroshToolsExtension;
use Frosh\Tools\DependencyInjection\SymfonyConfigCompilerPass;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

if (file_exists($vendorPath = __DIR__ . '/../vendor/autoload.php')) {
    require_once $vendorPath;
}

class FroshTools extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new CacheCompilerPass());
        $container->addCompilerPass(new TaskLoggingMiddlewareCompilerPass());
        $container->addCompilerPass(new SymfonyConfigCompilerPass());
    }

    public function createContainerExtension(): FroshToolsExtension
    {
        return new FroshToolsExtension();
    }
}
