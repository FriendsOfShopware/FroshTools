<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\ComposerAudit\ComposerAuditService;
use Frosh\Tools\Components\ComposerAudit\ComposerGraphService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class ComposerAuditController extends AbstractController
{
    public function __construct(
        private readonly ComposerAuditService $composerAuditService,
        private readonly ComposerGraphService $composerGraphService,
    ) {
    }

    #[Route(path: '/composer-audit', name: 'api.frosh.tools.composer-audit', methods: ['GET'])]
    public function audit(Request $request): JsonResponse
    {
        $forceRefresh = $request->query->getBoolean('refresh');

        return new JsonResponse($this->composerAuditService->audit($forceRefresh));
    }

    #[Route(path: '/composer-graph', name: 'api.frosh.tools.composer-graph', methods: ['GET'])]
    public function graph(Request $request): Response
    {
        $packages = \array_filter((array)$request->query->all('packages'), 'is_string');
        $withDevPackages = $request->query->getBoolean('withDevDependencies');
        $strict = $request->query->getBoolean('strict', true);
        $forceRefresh = $request->query->getBoolean('refresh');

        return new Response($this->composerGraphService->graph($packages, $withDevPackages, $strict, $forceRefresh), headers: [
            'Content-Type' => 'image/svg+xml',
        ]);
    }
}
