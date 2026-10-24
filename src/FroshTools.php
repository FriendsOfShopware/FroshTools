<?php

declare(strict_types=1);

namespace Frosh\Tools;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\ShopmonIntegrationService;
use Frosh\Tools\DependencyInjection\CacheCompilerPass;
use Frosh\Tools\DependencyInjection\DisableElasticsearchCompilerPass;
use Frosh\Tools\DependencyInjection\FroshToolsExtension;
use Frosh\Tools\DependencyInjection\SymfonyConfigCompilerPass;
use Frosh\Tools\DependencyInjection\WhenClassMissingCompilerPass;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\Integration\IntegrationCollection;
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

    private function initializeShopmon(Context $context): void
    {
        \assert($this->container !== null);

        // The plugin's services may not be registered yet during installation.
        /** @var EntityRepository<IntegrationCollection> $integrationRepository */
        $integrationRepository = $this->container->get('integration.repository');
        /** @var EntityRepository<AclRoleCollection> $aclRoleRepository */
        $aclRoleRepository = $this->container->get('acl_role.repository');
        $systemConfigService = $this->container->get(SystemConfigService::class);
        \assert($systemConfigService instanceof SystemConfigService);

        $service = new ShopmonIntegrationService(
            $integrationRepository,
            $aclRoleRepository,
            $systemConfigService,
        );
        $service->initialize($context);
    }
}
