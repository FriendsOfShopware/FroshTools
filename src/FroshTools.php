<?php declare(strict_types=1);

namespace Frosh\Tools;

use Composer\Autoload\ClassLoader;
use Frosh\Tools\Components\Lightningcss\Compiler;
use Frosh\Tools\Components\Messenger\TaskLoggingMiddlewareCompilerPass;
use Frosh\Tools\DependencyInjection\CacheCompilerPass;
use Frosh\Tools\DependencyInjection\DisableElasticsearchCompilerPass;
use Frosh\Tools\DependencyInjection\FroshToolsExtension;
use Frosh\Tools\DependencyInjection\SymfonyConfigCompilerPass;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Kernel;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class FroshTools extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new CacheCompilerPass());
        $container->addCompilerPass(new TaskLoggingMiddlewareCompilerPass());
        $container->addCompilerPass(new SymfonyConfigCompilerPass());
        $container->addCompilerPass(new DisableElasticsearchCompilerPass());

        $this->buildConfig($container);
    }

    public function createContainerExtension(): FroshToolsExtension
    {
        return new FroshToolsExtension();
    }

    private function buildConfig(ContainerBuilder $container): void
    {
        $locator = new FileLocator('Resources/config');

        $resolver = new LoaderResolver([
            new YamlFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
        ]);

        $configLoader = new DelegatingLoader($resolver);

        $confDir = $this->getPath() . '/Resources/config';

        $configLoader->load($confDir . '/{packages}/*' . Kernel::CONFIG_EXTS, 'glob');
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->container->hasParameter('frosh_tools.storefront.lightningcss.enabled') && $this->container->getParameter('frosh_tools.storefront.lightningcss.enabled')) {
            Compiler::setApiURL($this->container->getParameter('frosh_tools.storefront.lightningcss.api_url'));
            Compiler::setBrowserList($this->container->getParameter('frosh_tools.storefront.lightningcss.browserlist'));
            Compiler::setLogger($this->container->get('logger'));

            if (!class_exists('\Padaliyajay\PHPAutoprefixer\Autoprefixer', false)) {
                class_alias(Compiler::class, '\Padaliyajay\PHPAutoprefixer\Autoprefixer');
            }
        }
    }
}
