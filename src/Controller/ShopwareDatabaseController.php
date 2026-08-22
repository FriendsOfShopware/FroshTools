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

/**
 * Provide integration for `swdb.dev` Shopware version comparison.
 */
#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class ShopwareDatabaseController extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'kernel.shopware_version')]
        private readonly string $shopwareVersion,
        private readonly DatabaseDiffService $databaseDiffService,
        private readonly DatabaseIntrospectionService $databaseIntrospectionService,
    ) {
    }

    #[Route(path: '/shopware-database-diff/available-versions', name: 'api.frosh.tools.shopware-database-diff.version', methods: ['GET'])]
    public function fetchAvailableVersions(): JsonResponse
    {
        return new JsonResponse($this->databaseDiffService->getAvailableVersions());
    }

    #[Route(path: '/shopware-database-diff/{version}', name: 'api.frosh.tools.shopware-database-diff.show', methods: ['GET'])]
    public function showDatabaseDiff(Request $request, string $version): JsonResponse
    {
        if ($request->query->getBoolean('introspection')) {
            $schemaA = $this->databaseIntrospectionService->getDatabaseSchema();
        } else {
            $shopwareVersion = $this->getShopwareVersion();

            if (Kernel::SHOPWARE_FALLBACK_VERSION === $shopwareVersion) {
                $shopwareVersion = \substr($shopwareVersion, 0,
                    // Only use "6.6", and append ".0.0".
                    \strpos($shopwareVersion, '.', 1 + \strpos($shopwareVersion, '.'))
                ) . '.0.0';
            }

            $schemaA = $this->databaseDiffService->getDatabaseSchema($shopwareVersion);
        }
        $schemaB = $this->databaseDiffService->getDatabaseSchema($version);

        return new JsonResponse($this->databaseDiffService->createSchemaDiff($schemaA, $schemaB));
    }

    private function getShopwareVersion(): string
    {
        $shopwareVersion = \str_replace('.9999999.9999999-dev', '.9999999-dev', $this->shopwareVersion);

        if (Kernel::SHOPWARE_FALLBACK_VERSION === $shopwareVersion) {
            return $shopwareVersion;
        }

        return $this->shopwareVersion;
    }
}
