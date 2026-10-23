<?php

declare(strict_types=1);

namespace Frosh\Tools;

use Frosh\Tools\Components\ShopmonIntegrationService;
use Frosh\Tools\DependencyInjection\CacheCompilerPass;
use Frosh\Tools\DependencyInjection\DisableElasticsearchCompilerPass;
use Frosh\Tools\DependencyInjection\FroshToolsExtension;
use Frosh\Tools\DependencyInjection\SymfonyConfigCompilerPass;
use Frosh\Tools\DependencyInjection\WhenClassMissingCompilerPass;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
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

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        $this->initializeShopmon($installContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);
        $this->initializeShopmon($updateContext->getContext());
    }

    private function initializeShopmon(Context $context): void
    {
        // The plugin's services may not be registered yet during installation.
        $service = new ShopmonIntegrationService(
            $this->container->get('integration.repository'),
            $this->container->get('acl_role.repository'),
            $this->container->get(SystemConfigService::class),
        );
        $service->initialize($context);
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
