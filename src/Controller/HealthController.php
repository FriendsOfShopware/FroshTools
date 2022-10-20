<?php declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\PerformanceCollection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/api/_action/frosh-tools", defaults={"_routeScope"={"api"}, "_acl"={"frosh_tools:read"}})
 */
class HealthController
{
    /** @var CheckerInterface[] */
    private iterable $healthCheckers;

    /** @var CheckerInterface[] */
    private iterable $performanceCheckers;

    public function __construct(iterable $healthCheckers, iterable $performanceCheckers)
    {
        $this->healthCheckers = $healthCheckers;
        $this->performanceCheckers = $performanceCheckers;
    }

    /**
     * @Route(path="/health/status", methods={"GET"}, name="api.frosh.tools.health.status")
     */
    public function status(): JsonResponse
    {
        $collection = new HealthCollection();
        foreach ($this->healthCheckers as $checker) {
            $checker->collect($collection);
        }

        return new JsonResponse($collection);
    }

    /**
     * @Route(path="/performance/status", methods={"GET"}, name="api.frosh.tools.performance.status")
     */
    public function performanceStatus(): JsonResponse
    {
        $collection = new PerformanceCollection();
        foreach ($this->performanceCheckers as $checker) {
            $checker->collect($collection);
        }

        return new JsonResponse($collection);
    }
}
