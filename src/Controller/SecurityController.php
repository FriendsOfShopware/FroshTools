<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Acl\FroshToolsPrivileges;
use Frosh\Tools\Components\Sbom\CycloneDxSbomGenerator;
use Frosh\Tools\Components\Security\AdminAccountService;
use Frosh\Tools\Components\Security\Checker\SecurityCheckerInterface;
use Frosh\Tools\Components\Security\SecurityCollection;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => [FroshToolsPrivileges::SECURITY_READ]])]
class SecurityController extends AbstractController
{
    /**
     * @param SecurityCheckerInterface[] $securityCheckers
     */
    public function __construct(
        #[AutowireIterator('frosh_tools.security_checker')]
        private readonly iterable $securityCheckers,
        #[Autowire(param: 'frosh_tools.checker.disabled_checks')]
        private readonly array $ignoredChecks,
        private readonly CycloneDxSbomGenerator $sbomGenerator,
        private readonly AdminAccountService $adminAccountService,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route(path: '/security/status', name: 'api.frosh.tools.security.status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $collection = new SecurityCollection();
        foreach ($this->securityCheckers as $checker) {
            $checker->collect($collection);
        }

        $collection->removeByIds($this->ignoredChecks);
        $collection->sortBySeverity();

        return new JsonResponse([
            'summary' => $collection->countBySeverity(),
            'findings' => $collection,
        ]);
    }

    /**
     * Inventory of everything that can authenticate against the Admin API, for
     * external monitoring to diff against the previous snapshot and alert on new
     * accounts or privilege escalation.
     *
     * Contains no personal data by design - see {@see AdminAccountService}.
     */
    #[Route(path: '/security/accounts', name: 'api.frosh.tools.security.accounts', methods: ['GET'])]
    public function accounts(): JsonResponse
    {
        $users = $this->adminAccountService->getUsers();
        $integrations = $this->adminAccountService->getIntegrations();
        $aclRoles = $this->adminAccountService->getAclRoles();

        $activeIntegrations = array_filter($integrations, static fn (array $i): bool => $i['deletedAt'] === null);

        return new JsonResponse([
            'generatedAt' => $this->clock->now()->format(\DATE_ATOM),
            'summary' => [
                'users' => \count($users),
                'adminUsers' => \count(array_filter($users, static fn (array $u): bool => $u['admin'] === true)),
                'activeUsers' => \count(array_filter($users, static fn (array $u): bool => $u['active'] === true)),
                'integrations' => \count($activeIntegrations),
                'adminIntegrations' => \count(array_filter($activeIntegrations, static fn (array $i): bool => $i['admin'] === true)),
                'manualIntegrations' => \count(array_filter($activeIntegrations, static fn (array $i): bool => $i['managedByApp'] === false)),
                'aclRoles' => \count($aclRoles),
            ],
            'users' => $users,
            'integrations' => $integrations,
            'aclRoles' => $aclRoles,
        ]);
    }

    #[Route(path: '/security/sbom', name: 'api.frosh.tools.security.sbom', methods: ['GET'])]
    public function sbom(Request $request): Response
    {
        $includeDev = $request->query->getBoolean('includeDev');
        $json = $this->sbomGenerator->generateJson($includeDev);

        return new Response($json, Response::HTTP_OK, [
            'Content-Type' => 'application/vnd.cyclonedx+json',
            'Content-Disposition' => 'attachment; filename="sbom.cdx.json"',
        ]);
    }
}
