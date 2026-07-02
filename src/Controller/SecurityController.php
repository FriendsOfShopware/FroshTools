<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\Security\Checker\SecurityCheckerInterface;
use Frosh\Tools\Components\Security\SecurityCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
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
}
