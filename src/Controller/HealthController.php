<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Acl\FroshToolsPrivileges;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\DataIntegrityCollection;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\PerformanceCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => [FroshToolsPrivileges::READ]])]
class HealthController extends AbstractController
{
    /**
     * @param CheckerInterface[] $healthCheckers
     * @param CheckerInterface[] $performanceCheckers
     * @param CheckerInterface[] $dataIntegrityCheckers
     */
    public function __construct(
        #[AutowireIterator('frosh_tools.health_checker')]
        private readonly iterable $healthCheckers,
        #[AutowireIterator('frosh_tools.performance_checker')]
        private readonly iterable $performanceCheckers,
        #[AutowireIterator('frosh_tools.data_integrity_checker')]
        private readonly iterable $dataIntegrityCheckers,
        private readonly CacheInterface $cacheObject,
        #[Autowire(param: 'frosh_tools.checker.disabled_checks')]
        private readonly array $ignoredChecks,
    ) {
    }

    #[Route(path: '/health/status', name: 'api.frosh.tools.health.status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $collection = new HealthCollection();
        foreach ($this->healthCheckers as $checker) {
            $checker->collect($collection);
        }

        $collection->sortByState();
        $collection->removeByIds($this->ignoredChecks);

        return new JsonResponse($collection);
    }

    #[Route(path: '/performance/status', name: 'api.frosh.tools.performance.status', methods: ['GET'])]
    public function performanceStatus(): JsonResponse
    {
        $collection = new PerformanceCollection();
        foreach ($this->performanceCheckers as $checker) {
            $checker->collect($collection);
        }

        $collection->sortByState();
        $collection->removeByIds($this->ignoredChecks);

        return new JsonResponse($collection);
    }

    #[Route(path: '/data-integrity/status', name: 'api.frosh.tools.data-integrity.status', methods: ['GET'])]
    public function dataIntegrityStatus(): JsonResponse
    {
        $collection = new DataIntegrityCollection();
        foreach ($this->dataIntegrityCheckers as $checker) {
            $checker->collect($collection);
        }

        $collection->sortByState();
        $collection->removeByIds($this->ignoredChecks);

        return new JsonResponse($collection);
    }

    #[Route(path: '/health-ping/status', name: 'api.frosh.tools.health-ping.status', methods: ['GET'])]
    public function pingStatus(): JsonResponse
    {
        return $this->cacheObject->get('health-ping', function (ItemInterface $cacheItem) {
            $cacheItem->expiresAfter(59);

            return $this->status();
        });
    }
}
