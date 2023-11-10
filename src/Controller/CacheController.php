<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\CacheHelper;
use Frosh\Tools\Components\CacheRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class CacheController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.cache_dir%')]
        private readonly string $cacheDir,
        private readonly CacheRegistry $cacheRegistry
    ) {}

    #[Route(path: '/cache', name: 'api.frosh.tools.cache.get', methods: ['GET'])]
    public function cacheStatistics(): JsonResponse
    {
        $cacheFolder = \dirname($this->cacheDir);
        $folders = scandir($cacheFolder, \SCANDIR_SORT_ASCENDING) ?: [];

        $result = [];

        foreach ($folders as $folder) {
            if ($folder[0] === '.') {
                continue;
            }

            $cacheDir = $cacheFolder . '/' . $folder;
            $result[] = [
                'name' => $folder,
                'active' => $folder === basename($this->cacheDir),
                'size' => CacheHelper::getSize($cacheDir),
                'freeSpace' => disk_free_space($cacheDir),
                'type' => 'Filesystem',
            ];
        }

        foreach ($this->cacheRegistry->all() as $name => $adapter) {
            $result[] = [
                'name' => $name,
                'active' => true,
                'size' => $adapter->getSize(),
                'type' => $adapter->getType(),
                'freeSpace' => $adapter->getFreeSize(),
            ];
        }

        $activeColumns = array_column($result, 'active');
        $freeSpaceColumns = array_column($result, 'freeSpace');
        $sizeColumns = array_column($result, 'size');

        array_multisort(
            $activeColumns,
            \SORT_DESC,
            $freeSpaceColumns,
            \SORT_ASC,
            $sizeColumns,
            \SORT_DESC,
            $result
        );

        return new JsonResponse($result);
    }

    #[Route(path: '/cache/{folder}', name: 'api.frosh.tools.cache.clear', methods: ['DELETE'])]
    public function clearCache(string $folder): JsonResponse
    {
        if ($this->cacheRegistry->has($folder)) {
            $this->cacheRegistry->get($folder)->clear();
        } else {
            CacheHelper::removeDir(\dirname($this->cacheDir) . '/' . basename($folder));
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
