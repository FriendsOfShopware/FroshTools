<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\DatabaseDiff\DatabaseDiffService;
use Frosh\Tools\Components\DatabaseDiff\DatabaseIntrospectionService;
use Shopware\Core\Kernel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Provide integration for `swdb.dev` Shopware version comparison.
 */
#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class ShopwareDatabaseController extends AbstractController
{
    private const CACHE_KEY_AVAILABLE_VERSIONS = 'frosh-tools-database-diff-available-versions';
    private const CACHE_KEY_DIFF               = 'frosh-tools-database-diff';
    private const CACHE_TTL_SECONDS            = 3600;

    public function __construct(
        #[Autowire(param: 'kernel.shopware_version')]
        private readonly string $shopwareVersion,
        private readonly DatabaseDiffService $databaseDiffService,
        private readonly DatabaseIntrospectionService $databaseIntrospectionService,
        private readonly CacheInterface $cacheObject,
    ) {
    }

    #[Route(path: '/shopware-database-diff/available-versions', name: 'api.frosh.tools.shopware-database-diff.version', methods: ['GET'])]
    public function fetchAvailableVersions(Request $request): JsonResponse
    {
        if ($this->shopwareVersion === Kernel::SHOPWARE_FALLBACK_VERSION) {
            return new JsonResponse(['error' => 'Git version is not supported']);
        }

        if ($request->query->getBoolean('refresh')) {
            $this->cacheObject->deleteItem(self::CACHE_KEY_AVAILABLE_VERSIONS);
        }

        $availableVersions = $this->cacheObject->get(self::CACHE_KEY_AVAILABLE_VERSIONS, function (ItemInterface $cacheItem): array {
            $cacheItem->expiresAfter(self::CACHE_TTL_SECONDS);

            return $this->databaseDiffService->getAvailableVersions();
        });

        return new JsonResponse($availableVersions);
    }

    #[Route(path: '/shopware-database-diff/{version}', name: 'api.frosh.tools.shopware-database-diff.show', methods: ['GET'])]
    public function showDatabaseDiff(Request $request, string $version): JsonResponse
    {
        if ($this->shopwareVersion === Kernel::SHOPWARE_FALLBACK_VERSION) {
            return new JsonResponse(['error' => 'Git version is not supported']);
        }

        if ($introspection = $request->query->getBoolean('introspection')) {
            $shopwareVersion = null;
        } else {
            $shopwareVersion = $this->shopwareVersion;

            if (Kernel::SHOPWARE_FALLBACK_VERSION === \str_replace('.9999999.9999999-dev', '.9999999-dev', $shopwareVersion)) {
                $shopwareVersion = \substr($shopwareVersion, 0,
                        // Only use "6.6", and append ".0.0".
                        \strpos($shopwareVersion, '.', 1 + \strpos($shopwareVersion, '.'))
                    ) . '.0.0';
            }
        }

        $version  = $this->databaseDiffService->parseVersionSlug($version);
        $cacheKey = \sprintf('%s(%s:%s)%d', self::CACHE_KEY_DIFF,
            $shopwareVersion ?? 'introspection', $version, (int)$introspection,
        );

        if ($request->query->getBoolean('refresh')) {
            $this->cacheObject->deleteItem($cacheKey);
        }

        $diff = $this->cacheObject->get($cacheKey, function (ItemInterface $cacheItem) use ($introspection, $shopwareVersion, $version) {
            $cacheItem->expiresAfter(self::CACHE_TTL_SECONDS);

            $schemaA = !$introspection
                ? $this->databaseDiffService->getDatabaseSchema($shopwareVersion)
                : $this->databaseIntrospectionService->getDatabaseSchema();
            $schemaB = $this->databaseDiffService->getDatabaseSchema($version);

            return $this->databaseDiffService->createSchemaDiff($schemaA, $schemaB);
        });

        return new JsonResponse($diff);
    }
}
