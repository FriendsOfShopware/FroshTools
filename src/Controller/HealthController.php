<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\PerformanceCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class HealthController extends AbstractController
{
    /**
     * @param CheckerInterface[] $healthCheckers
     * @param CheckerInterface[] $performanceCheckers
     */
    public function __construct(
        #[TaggedIterator('frosh_tools.health_checker')]
        private readonly iterable $healthCheckers,
        #[TaggedIterator('frosh_tools.performance_checker')]
        private readonly iterable $performanceCheckers
    ) {}

    #[Route(path: '/health/status', name: 'api.frosh.tools.health.status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $collection = new HealthCollection();
        foreach ($this->healthCheckers as $checker) {
            $checker->collect($collection);
        }

        return new JsonResponse($collection);
    }

    #[Route(path: '/performance/status', name: 'api.frosh.tools.performance.status', methods: ['GET'])]
    public function performanceStatus(): JsonResponse
    {
        $collection = new PerformanceCollection();
        foreach ($this->performanceCheckers as $checker) {
            $checker->collect($collection);
        }

        return new JsonResponse($collection);
    }
}
