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
use Shopware\Core\System\Integration\IntegrationEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ShopmonIntegrationService
{
    private const INTEGRATION_ID = 'c2f0a1d4b6e84f309a5d7c1e8b2f4a60';
    private const LEGACY_INTEGRATION_ID = 'a1b2c3d4e5f6789012345678901234ab';
    private const CONFIG_KEY = 'FroshTools.config.shopmonIntegrationData';
    private const LEGACY_CONFIG_KEY = 'FroshShopmon.config.integrationData';

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
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function setup(Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context): void {
            $integrations = $this->findIntegrations($context);
            $integration = $integrations->get(self::INTEGRATION_ID) ?? $integrations->first();
            $id = $integration?->getId() ?? self::INTEGRATION_ID;

            if ($integration !== null && ($encoded = $this->getIntegrationData($integration)) !== '') {
                $this->updatePermissions($id, $context);
                $this->systemConfigService->set(self::CONFIG_KEY, $encoded);

                return;
            }

            $accessKey = EnvironmentHelper::getVariable('SHOPMON_ACCESS_KEY', '') ?: AccessKeyHelper::generateAccessKey('integration');
            $secretAccessKey = EnvironmentHelper::getVariable('SHOPMON_ACCESS_SECRET', '') ?: AccessKeyHelper::generateSecretAccessKey();

            $this->integrationRepository->upsert([[
                'id' => $id,
                'label' => 'Shopmon Integration (FroshTools)',
                'accessKey' => $accessKey,
                'secretAccessKey' => $secretAccessKey,
            ]], $context);
            $this->updatePermissions($id, $context);

            $this->systemConfigService->set(self::CONFIG_KEY, base64_encode(json_encode([
                'url' => EnvironmentHelper::getVariable('APP_URL', 'http://localhost'),
                'clientId' => $accessKey,
                'clientSecret' => $secretAccessKey,
            ], \JSON_THROW_ON_ERROR)));
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

                $integration = $integrations->get(self::INTEGRATION_ID) ?? $integrations->first();
                \assert($integration !== null);
                $encoded = $this->getIntegrationData($integration);

                if ($encoded !== '') {
                    $this->systemConfigService->set(self::CONFIG_KEY, $encoded);
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
            $integrations = $this->findIntegrations($context);
            $integration = $integrations->get(self::INTEGRATION_ID) ?? $integrations->first();
            $encoded = $integration !== null ? $this->getIntegrationData($integration) : '';

            if ($encoded === '') {
                return ['configured' => false];
            }

            $data = json_decode((string) base64_decode($encoded, true), true, 512, \JSON_THROW_ON_ERROR);

            return [
                'configured' => true,
                'shopUrl' => $data['url'] ?? '',
                'clientId' => $data['clientId'],
                'clientSecret' => $data['clientSecret'],
                'integrationData' => $encoded,
            ];
        });
    }

    public function remove(Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context): void {
            $ids = [['id' => self::INTEGRATION_ID], ['id' => self::LEGACY_INTEGRATION_ID]];
            $this->integrationRepository->delete($ids, $context);
            $this->aclRoleRepository->delete($ids, $context);
            $this->systemConfigService->delete(self::CONFIG_KEY);
            $this->systemConfigService->delete(self::LEGACY_CONFIG_KEY);
        });
    }

    private function findIntegrations(Context $context): IntegrationCollection
    {
        return $this->integrationRepository
            ->search(new Criteria([self::INTEGRATION_ID, self::LEGACY_INTEGRATION_ID]), $context)
            ->getEntities();
    }

    private function getIntegrationData(IntegrationEntity $integration): string
    {
        $keys = [self::CONFIG_KEY];

        if ($integration->getId() === self::LEGACY_INTEGRATION_ID) {
            $keys[] = self::LEGACY_CONFIG_KEY;
        }

        foreach ($keys as $key) {
            $encoded = $this->systemConfigService->getString($key);
            $decoded = base64_decode($encoded, true);
            $data = $decoded !== false ? json_decode($decoded, true) : null;

            if (\is_array($data)
                && ($data['clientId'] ?? null) === $integration->getAccessKey()
                && \is_string($data['clientSecret'] ?? null)
                && $data['clientSecret'] !== '') {
                return $encoded;
            }
        }

        return '';
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
