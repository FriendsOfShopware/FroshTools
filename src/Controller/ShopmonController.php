<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Integration\IntegrationCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class ShopmonController extends AbstractController
{
    // Fixed id keeps setup idempotent and lets us find/remove the integration again.
    private const INTEGRATION_ID = 'c2f0a1d4b6e84f309a5d7c1e8b2f4a60';
    private const CONFIG_KEY = 'FroshTools.config.shopmonIntegrationData';

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

    #[Route(path: '/shopmon', name: 'api.frosh.tools.shopmon.setup', methods: ['POST'])]
    public function setup(Context $context): JsonResponse
    {
        $accessKey = EnvironmentHelper::getVariable('SHOPMON_ACCESS_KEY', AccessKeyHelper::generateAccessKey('integration'));
        $secretAccessKey = EnvironmentHelper::getVariable('SHOPMON_ACCESS_SECRET', AccessKeyHelper::generateSecretAccessKey());

        // System scope, as admin users browsing the tools usually lack integration write privileges.
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($accessKey, $secretAccessKey): void {
            $this->integrationRepository->upsert([
                [
                    'id' => self::INTEGRATION_ID,
                    'label' => 'Shopmon Integration (FroshTools)',
                    'accessKey' => $accessKey,
                    'secretAccessKey' => $secretAccessKey,
                    'aclRoles' => [
                        [
                            'id' => self::INTEGRATION_ID,
                            'name' => 'frosh_shopmon_role',
                            'privileges' => [
                                'app:read',
                                'plugin:read',
                                'system_config:read',
                                'scheduled_task:read',
                                'frosh_tools:read',
                                'system:clear:cache',
                                'system:cache:info',
                                'scheduled_task:update',
                            ],
                        ],
                    ],
                ],
            ], $context);
        });

        $integrationData = [
            'url' => EnvironmentHelper::getVariable('APP_URL', 'http://localhost'),
            'clientId' => $accessKey,
            'clientSecret' => $secretAccessKey,
        ];

        $this->systemConfigService->set(
            self::CONFIG_KEY,
            base64_encode(json_encode($integrationData, \JSON_THROW_ON_ERROR)),
        );

        return new JsonResponse($this->buildStatus($context));
    }

    #[Route(path: '/shopmon', name: 'api.frosh.tools.shopmon.status', methods: ['GET'])]
    public function status(Context $context): JsonResponse
    {
        return new JsonResponse($this->buildStatus($context));
    }

    #[Route(path: '/shopmon', name: 'api.frosh.tools.shopmon.remove', methods: ['DELETE'])]
    public function remove(Context $context): JsonResponse
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context): void {
            try {
                $this->integrationRepository->delete([['id' => self::INTEGRATION_ID]], $context);
                $this->aclRoleRepository->delete([['id' => self::INTEGRATION_ID]], $context);
            } catch (\Exception) {
                // Integration might not exist anymore, ignore
            }
        });

        $this->systemConfigService->delete(self::CONFIG_KEY);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStatus(Context $context): array
    {
        $encoded = $this->systemConfigService->getString(self::CONFIG_KEY);

        if ($encoded === '') {
            return ['configured' => false];
        }

        $decoded = base64_decode($encoded, true);
        $data = $decoded !== false ? json_decode($decoded, true) : null;

        if (!\is_array($data)) {
            return ['configured' => false];
        }

        // Read in system scope to match the write path; admin users browsing the
        // tools usually lack integration:read and would otherwise see "not configured".
        $integration = null;
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use (&$integration): void {
            $integration = $this->integrationRepository
                ->search(new Criteria([self::INTEGRATION_ID]), $context)
                ->first();
        });

        if ($integration === null) {
            return ['configured' => false];
        }

        return [
            'configured' => true,
            'shopUrl' => $data['url'] ?? '',
            'clientId' => $data['clientId'] ?? '',
            'clientSecret' => $data['clientSecret'] ?? '',
            'integrationData' => $encoded,
        ];
    }
}
