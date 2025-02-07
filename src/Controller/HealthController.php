<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\PerformanceCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class HealthController extends AbstractController
{
    /**
     * @param CheckerInterface[] $healthCheckers
     * @param CheckerInterface[] $performanceCheckers
     */
    public function __construct(
        #[AutowireIterator('frosh_tools.health_checker')]
        private readonly iterable $healthCheckers,
        #[AutowireIterator('frosh_tools.performance_checker')]
        private readonly iterable $performanceCheckers,
        private readonly CacheInterface $cacheObject,
        private readonly LoggerInterface $froshToolsLogger,
        private readonly SystemConfigService $configService,
    ) {}

    #[Route(path: '/health/status', name: 'api.frosh.tools.health.status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return new JsonResponse($this->getHealthStatus());
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

    #[Route(path: '/health/check', name: 'api.frosh.tools.health-ping.status', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        $healthStatus = $this->getHealthCachedStatus();

        return new JsonResponse(['state' => $healthStatus->getState()]);
    }

    private function getHealthStatus(): HealthCollection
    {
        $collection = new HealthCollection();
        foreach ($this->healthCheckers as $checker) {
            $checker->collect($collection);
        }

        return $collection;
    }

    private function getHealthCachedStatus(): HealthCollection
    {
        return $this->cacheObject->get('health-check', function (ItemInterface $cacheItem) {
            $cacheItem->expiresAfter(59);

            $healthStatus = $this->getHealthStatus();

            if ($this->configService->getBool('FroshTools.config.healthLogger')) {
                foreach ([SettingsResult::ERROR, SettingsResult::WARNING] as $state) {
                    $healthStatus = $healthStatus->filter(function (SettingsResult $result) use ($state) {
                        return $result->state === $state;
                    });

                    if ($healthStatus->count() > 0) {
                        if ($state === SettingsResult::ERROR) {
                            $this->froshToolsLogger->error('Health check', ['results' => $healthStatus]);
                        } else {
                            $this->froshToolsLogger->warning('Health check', ['results' => $healthStatus]);
                        }
                    }
                }
            }

            return $healthStatus;
        });
    }
}
