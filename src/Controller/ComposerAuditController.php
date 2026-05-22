<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\ComposerAudit\ComposerAuditService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class ComposerAuditController extends AbstractController
{
    public function __construct(
        private readonly ComposerAuditService $composerAuditService,
    ) {
    }

    #[Route(path: '/composer-audit', name: 'api.frosh.tools.composer-audit', methods: ['GET'])]
    public function audit(Request $request): JsonResponse
    {
        $forceRefresh = $request->query->getBoolean('refresh');

        return new JsonResponse($this->composerAuditService->audit($forceRefresh));
    }
}
