<?php

declare(strict_types=1);

namespace Frosh\Tools;

use Doctrine\DBAL\Connection;
use Frosh\Tools\DependencyInjection\CacheCompilerPass;
use Frosh\Tools\DependencyInjection\DisableElasticsearchCompilerPass;
use Frosh\Tools\DependencyInjection\FroshToolsExtension;
use Frosh\Tools\DependencyInjection\SymfonyConfigCompilerPass;
use Frosh\Tools\DependencyInjection\WhenClassMissingCompilerPass;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FroshTools extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new CacheCompilerPass());
        $container->addCompilerPass(new SymfonyConfigCompilerPass());
        $container->addCompilerPass(new DisableElasticsearchCompilerPass());
        WhenClassMissingCompilerPass::configure($container);
        $container->addCompilerPass(new WhenClassMissingCompilerPass());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);
        if ($uninstallContext->keepUserData()) {
            return;
        }
        \assert($this->container !== null);
        $connection = $this->container->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('DROP TABLE IF EXISTS frosh_tools_security_activity');
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
