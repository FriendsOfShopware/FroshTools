<?php

declare(strict_types=1);

namespace Frosh\Tools\Components;

use Frosh\Tools\Acl\FroshToolsPrivileges;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Integration\IntegrationCollection;

class ShopmonIntegrationService
{
    private const INTEGRATION_ID = 'c2f0a1d4b6e84f309a5d7c1e8b2f4a60';
    private const LEGACY_INTEGRATION_ID = 'a1b2c3d4e5f6789012345678901234ab';

    private const PERMISSIONS = [
        'app:read',
        'plugin:read',
        'scheduled_task:read',
        'scheduled_task:update',
        'message_queue_stats:read',
        FroshToolsPrivileges::READ,
        FroshToolsPrivileges::APPS_READ,
        FroshToolsPrivileges::CACHE_READ,
        FroshToolsPrivileges::CACHE_UPDATE,
        FroshToolsPrivileges::SCHEDULED_TASK_READ,
        FroshToolsPrivileges::SCHEDULED_TASK_UPDATE,
        FroshToolsPrivileges::SECURITY_READ,
        'system:clear:cache',
        'system:cache:info',
    ];

    /**
     * @param EntityRepository<IntegrationCollection> $integrationRepository
     * @param EntityRepository<AclRoleCollection> $aclRoleRepository
     */
    public function __construct(
        private readonly EntityRepository $integrationRepository,
        private readonly EntityRepository $aclRoleRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function setup(Context $context): array
    {
        return $context->scope(Context::SYSTEM_SCOPE, function (Context $context): array {
            $integrations = $this->findIntegrations($context);
            $integration = $integrations->get(self::INTEGRATION_ID) ?? $integrations->first();
            $id = $integration?->getId() ?? self::INTEGRATION_ID;

            if ($integration !== null) {
                $this->updatePermissions($id, $context);

                return ['configured' => true];
            }

            $accessKey = EnvironmentHelper::getVariable('SHOPMON_ACCESS_KEY', '');
            $secretAccessKey = EnvironmentHelper::getVariable('SHOPMON_ACCESS_SECRET', '');
            $accessKey = $accessKey !== '' ? $accessKey : AccessKeyHelper::generateAccessKey('integration');
            $secretAccessKey = $secretAccessKey !== '' ? $secretAccessKey : AccessKeyHelper::generateSecretAccessKey();

            $this->integrationRepository->upsert([[
                'id' => $id,
                'label' => 'Shopmon Integration (FroshTools)',
                'accessKey' => $accessKey,
                'secretAccessKey' => $secretAccessKey,
            ]], $context);
            $this->updatePermissions($id, $context);

            $data = [
                'url' => EnvironmentHelper::getVariable('APP_URL', 'http://localhost'),
                'clientId' => $accessKey,
                'clientSecret' => $secretAccessKey,
            ];

            return [
                'configured' => true,
                'shopUrl' => $data['url'],
                'clientId' => $accessKey,
                'clientSecret' => $secretAccessKey,
                'integrationData' => base64_encode(json_encode($data, \JSON_THROW_ON_ERROR)),
            ];
        });
    }

    /**
     * Refresh existing integrations without rotating credentials; opt in to creation via environment variables.
     */
    public function initialize(Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context): void {
            $integrations = $this->findIntegrations($context);

            if ($integrations->count() > 0) {
                foreach ($integrations as $integration) {
                    $this->updatePermissions($integration->getId(), $context);
                }

                return;
            }

            if (EnvironmentHelper::getVariable('SHOPMON_ACCESS_KEY', '') !== ''
                && EnvironmentHelper::getVariable('SHOPMON_ACCESS_SECRET', '') !== '') {
                $this->setup($context);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function status(Context $context): array
    {
        return $context->scope(Context::SYSTEM_SCOPE, function (Context $context): array {
            return ['configured' => $this->findIntegrations($context)->count() > 0];
        });
    }

    public function remove(Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context): void {
            $ids = [['id' => self::INTEGRATION_ID], ['id' => self::LEGACY_INTEGRATION_ID]];
            $this->integrationRepository->delete($ids, $context);
            $this->aclRoleRepository->delete($ids, $context);
        });
    }

    private function findIntegrations(Context $context): IntegrationCollection
    {
        return $this->integrationRepository
            ->search(new Criteria([self::INTEGRATION_ID, self::LEGACY_INTEGRATION_ID]), $context)
            ->getEntities();
    }

    private function updatePermissions(string $id, Context $context): void
    {
        $this->integrationRepository->update([[
            'id' => $id,
            'aclRoles' => [[
                'id' => $id,
                'name' => 'frosh_shopmon_role',
                'privileges' => self::PERMISSIONS,
            ]],
        ]], $context);
    }
}
