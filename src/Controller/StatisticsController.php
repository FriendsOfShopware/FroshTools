<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\CacheStatisticsService;
use Frosh\Tools\Components\DatabaseStatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/_action/frosh-tools/statistics', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class StatisticsController extends AbstractController
{
    public function __construct(
        private readonly CacheStatisticsService $cacheStatisticsService,
        private readonly DatabaseStatisticsService $databaseStatisticsService,
    ) {
    }

    #[Route(path: '/cache', name: 'api.frosh.tools.statistics.cache', methods: ['GET'])]
    public function cacheStatistics(): JsonResponse
    {
        return new JsonResponse([
            'opcache' => $this->cacheStatisticsService->getOpcacheStatistics(),
            'apcu' => $this->cacheStatisticsService->getApcuStatistics(),
            'redis' => $this->cacheStatisticsService->getRedisStatistics(),
        ]);
    }

    #[Route(path: '/database', name: 'api.frosh.tools.statistics.database', methods: ['GET'])]
    public function databaseStatistics(): JsonResponse
    {
        return new JsonResponse([
            'server' => $this->databaseStatisticsService->getServerInfo(),
            'tables' => $this->databaseStatisticsService->getTableStatistics(),
            'globalStatus' => $this->databaseStatisticsService->getGlobalStatus(),
        ]);
    }
}
