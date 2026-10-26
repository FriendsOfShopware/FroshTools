<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Acl\FroshToolsPrivileges;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\AffectedEntitiesProviderInterface;
use Frosh\Tools\Components\Health\DataIntegrityCollection;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\PerformanceCollection;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => [FroshToolsPrivileges::READ]])]
class HealthController extends AbstractController
{
    private const DATA_INTEGRITY_CACHE_KEY = 'frosh-tools-data-integrity-status';

    private const DATA_INTEGRITY_CACHE_TTL_SECONDS = 300;

    private const DATA_INTEGRITY_AFFECTED_MAX_LIMIT = 100;

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
    public function dataIntegrityStatus(Request $request): JsonResponse
    {
        if ($request->query->getBoolean('refresh')) {
            $this->cacheObject->delete(self::DATA_INTEGRITY_CACHE_KEY);
        }

        return $this->cacheObject->get(self::DATA_INTEGRITY_CACHE_KEY, function (ItemInterface $cacheItem) {
            $cacheItem->expiresAfter(self::DATA_INTEGRITY_CACHE_TTL_SECONDS);

            $collection = new DataIntegrityCollection();
            foreach ($this->dataIntegrityCheckers as $checker) {
                $checker->collect($collection);
            }

            $collection->sortByState();
            $collection->removeByIds($this->ignoredChecks);

            return new JsonResponse($collection);
        });
    }

    #[Route(path: '/data-integrity/{id}/affected', name: 'api.frosh.tools.data-integrity.affected', methods: ['GET'])]
    public function dataIntegrityAffected(string $id, Request $request, Context $context): JsonResponse
    {
        $limit = max(1, min(self::DATA_INTEGRITY_AFFECTED_MAX_LIMIT, $request->query->getInt('limit', 25)));
        $page = max(1, min(intdiv(\PHP_INT_MAX, $limit), $request->query->getInt('page', 1)));

        foreach ($this->dataIntegrityCheckers as $checker) {
            if (!$checker instanceof AffectedEntitiesProviderInterface || $checker->getId() !== $id || \in_array($id, $this->ignoredChecks, true)) {
                continue;
            }

            $privilege = $checker->getAffectedEntity() . ':' . AclRoleDefinition::PRIVILEGE_READ;
            if (!$context->isAllowed($privilege)) {
                return new JsonResponse(['error' => \sprintf('Missing privilege "%s"', $privilege)], Response::HTTP_FORBIDDEN);
            }

            $affected = $checker->getAffectedEntities($limit, ($page - 1) * $limit);
            if ($affected->entity !== $checker->getAffectedEntity()) {
                throw new \LogicException(\sprintf('Data integrity check "%s" declares entity "%s" but returned "%s"', $id, $checker->getAffectedEntity(), $affected->entity));
            }

            return new JsonResponse([
                ...$affected->jsonSerialize(),
                'page' => $page,
                'limit' => $limit,
            ]);
        }

        return new JsonResponse(['error' => \sprintf('Unknown data integrity check "%s"', $id)], Response::HTTP_NOT_FOUND);
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
