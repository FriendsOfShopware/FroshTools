<?php declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Shopware\Core\Framework\Feature;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class FeatureFlagController
{
    #[Route(path: '/feature-flag/list', name: 'api.frosh.tools.feature-flag.list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $featureFlags = Feature::getRegisteredFeatures();

        foreach (array_keys($featureFlags) as $featureFlag) {
            $featureFlags[$featureFlag]['flag'] = $featureFlag;
            $featureFlags[$featureFlag]['active'] = Feature::isActive($featureFlag);
        }

        $activeColumns = array_column($featureFlags, 'active');
        $flagColumns = array_column($featureFlags, 'flag');
        array_multisort(
            $activeColumns,
            \SORT_DESC,
            $flagColumns,
            \SORT_ASC,
            $featureFlags
        );

        return new JsonResponse(array_values($featureFlags));
    }
}
