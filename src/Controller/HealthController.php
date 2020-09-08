<?php declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 * @Route(path="/api/{version}/_action/frosh-tools")
 */
class HealthController
{
    /**
     * @var CheckerInterface[]
     */
    private $checkers;

    public function __construct(iterable $checkers)
    {
        $this->checkers = $checkers;
    }

    /**
     * @Route(path="/health/status", methods={"GET"}, name="api.frosh.tools.health.status")
     */
    public function status(): JsonResponse
    {
        $collection = new HealthCollection();
        foreach ($this->checkers as $checker) {
            $checker->collect($collection);
        }

        return new JsonResponse($collection);
    }
}
