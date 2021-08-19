<?php declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\CacheAdapter;
use Frosh\Tools\Components\CacheHelper;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 * @Route(path="/api/_action/frosh-tools")
 */
class CacheController
{
    private string $cacheDir;
    private CacheAdapter $appCache;
    private CacheAdapter $httpCache;
    private CacheAdapter $objectCache;
    private CacheAdapter $tagsCache;

    public function __construct(
        string $cacheDir,
        CacheAdapter $appCache,
        CacheAdapter $httpCache,
        CacheAdapter $objectCache,
        CacheAdapter $tagsCache
    )
    {
        $this->cacheDir = $cacheDir;
        $this->appCache = $appCache;
        $this->httpCache = $httpCache;
        $this->objectCache = $objectCache;
        $this->tagsCache = $tagsCache;
    }

    /**
     * @Route(path="/cache", methods={"GET"}, name="api.frosh.tools.cache.get")
     */
    public function cacheStatistics(): JsonResponse
    {
        $cacheFolder = dirname($this->cacheDir);
        $folders = scandir($cacheFolder, \SCANDIR_SORT_ASCENDING);

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

        $result[] = [
            'name' => 'App Cache',
            'active' => true,
            'size' => $this->appCache->getSize(),
            'type' => $this->appCache->getType(),
            'freeSpace' => $this->appCache->getFreeSize(),
        ];

        $result[] = [
            'name' => 'Http Cache',
            'active' => true,
            'size' => $this->httpCache->getSize(),
            'type' => $this->httpCache->getType(),
            'freeSpace' => $this->httpCache->getFreeSize(),
        ];

        $result[] = [
            'name' => 'Object Cache',
            'active' => true,
            'size' => $this->objectCache->getSize(),
            'type' => $this->objectCache->getType(),
            'freeSpace' => $this->objectCache->getFreeSize(),
        ];

        $result[] = [
            'name' => 'Tags Cache',
            'active' => true,
            'size' => $this->tagsCache->getSize(),
            'type' => $this->tagsCache->getType(),
            'freeSpace' => $this->tagsCache->getFreeSize(),
        ];

        $activeColumns = array_column($result, 'active');
        $nameColumns = array_column($result, 'name');

        array_multisort($activeColumns, \SORT_DESC,
            $nameColumns, \SORT_ASC,
            $result);

        return new JsonResponse($result);
    }

    /**
     * @Route(path="/cache/{folder}", methods={"DELETE"}, name="api.frosh.tools.cache.clear")
     */
    public function clearCache(string $folder): JsonResponse
    {
        if ($folder === 'App Cache') {
            $this->appCache->clear();
        } elseif ($folder === 'Http Cache') {
            $this->httpCache->clear();
        } elseif ($folder === 'Object Cache') {
            $this->objectCache->clear();
        } elseif ($folder === 'Tags Cache') {
            $this->tagsCache->clear();
        } else {
            CacheHelper::removeDir(dirname($this->cacheDir) . '/' . basename($folder));
        }

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
