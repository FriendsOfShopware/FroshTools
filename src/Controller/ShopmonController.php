<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Acl\FroshToolsPrivileges;
use Frosh\Tools\Components\ShopmonIntegrationService;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => [FroshToolsPrivileges::SHOPMON_READ]])]
class ShopmonController extends AbstractController
{
    public function __construct(private readonly ShopmonIntegrationService $integrationService)
    {
    }

    #[Route(path: '/shopmon', name: 'api.frosh.tools.shopmon.setup', defaults: ['_acl' => [FroshToolsPrivileges::SHOPMON_UPDATE]], methods: ['POST'])]
    public function setup(Context $context): JsonResponse
    {
        $this->integrationService->setup($context);

        return new JsonResponse($this->integrationService->status($context));
    }

    #[Route(path: '/shopmon', name: 'api.frosh.tools.shopmon.status', methods: ['GET'])]
    public function status(Context $context): JsonResponse
    {
        return new JsonResponse($this->integrationService->status($context));
    }

    #[Route(path: '/shopmon', name: 'api.frosh.tools.shopmon.remove', defaults: ['_acl' => [FroshToolsPrivileges::SHOPMON_UPDATE]], methods: ['DELETE'])]
    public function remove(Context $context): JsonResponse
    {
        $this->integrationService->remove($context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
