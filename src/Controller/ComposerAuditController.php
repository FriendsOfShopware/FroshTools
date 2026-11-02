<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Acl\FroshToolsPrivileges;
use Frosh\Tools\Components\AcknowledgementStore;
use Frosh\Tools\Components\ComposerAudit\ComposerAuditService;
use Frosh\Tools\Components\Security\Checker\DependencyAuditChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => [FroshToolsPrivileges::SECURITY_READ]])]
class ComposerAuditController extends AbstractController
{
    public function __construct(
        private readonly ComposerAuditService $composerAuditService,
        private readonly AcknowledgementStore $acknowledgementStore,
    ) {
    }

    #[Route(path: '/composer-audit', name: 'api.frosh.tools.composer-audit', methods: ['GET'])]
    public function audit(Request $request): JsonResponse
    {
        $forceRefresh = $request->query->getBoolean('refresh');
        $result = $this->composerAuditService->audit($forceRefresh);
        $result['advisories'] = $this->withAcknowledgements($result['advisories']);

        return new JsonResponse($result);
    }

    /**
     * @param list<mixed> $advisories
     *
     * @return list<array<string, mixed>>
     */
    private function withAcknowledgements(array $advisories): array
    {
        $acknowledgements = $this->acknowledgementStore->all();
        $annotated = [];

        foreach ($advisories as $advisory) {
            if (!\is_array($advisory)) {
                continue;
            }

            $id = DependencyAuditChecker::findingId($advisory);
            $advisory['id'] = $id;
            $advisory['acknowledgement'] = $acknowledgements[$id] ?? '';
            $annotated[] = $advisory;
        }

        return $annotated;
    }
}
