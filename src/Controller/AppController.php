<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Acl\FroshToolsPrivileges;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\System\User\UserCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route(path: '/api/_action/frosh-tools/apps', defaults: ['_routeScope' => ['api'], '_acl' => [FroshToolsPrivileges::APPS_READ]])]
class AppController extends AbstractController
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     * @param EntityRepository<AppCollection> $appRepository
     * @param object|null $appUrlVerifier Shopware\Core\Framework\App\Url\AppUrlVerifier — only exists on Shopware >= 6.7,
     *                                    so it is injected as a plain object and probed with method_exists()
     */
    public function __construct(
        private readonly ShopIdProvider $shopIdProvider,
        private readonly StoreClient $storeClient,
        private readonly EntityRepository $userRepository,
        private readonly EntityRepository $appRepository,
        private readonly AbstractAppLifecycle $appLifecycle,
        private readonly HttpClientInterface $httpClient,
        private readonly ?object $appUrlVerifier = null,
    ) {
    }

    #[Route(path: '/status', name: 'api.frosh.tools.apps.status', methods: ['GET'])]
    public function status(Context $context): JsonResponse
    {
        return new JsonResponse([
            'appUrl' => $this->getAppUrl(),
            'reachability' => $this->getCachedReachability(),
            'store' => ['loggedIn' => $this->hasStoreToken($context)],
            'shopId' => $this->getShopId(),
            'apps' => $this->getInstalledApps($context),
        ]);
    }

    /**
     * Separate endpoint as it calls the external store API, which may be slow or unreachable.
     */
    #[Route(path: '/store-user-info', name: 'api.frosh.tools.apps.store_user_info', methods: ['GET'])]
    public function storeUserInfo(Context $context): JsonResponse
    {
        if (!$this->hasStoreToken($context)) {
            return new JsonResponse(['user' => null]);
        }

        try {
            $userInfo = $this->storeClient->userInfo($context);
        } catch (\Throwable) {
            // Missing/invalid token or the store API is unreachable.
            return new JsonResponse(['user' => null]);
        }

        return new JsonResponse([
            'user' => [
                'name' => \is_string($userInfo['name'] ?? null) ? $userInfo['name'] : null,
                'email' => \is_string($userInfo['email'] ?? null) ? $userInfo['email'] : null,
                'avatarUrl' => \is_string($userInfo['avatarUrl'] ?? null) ? $userInfo['avatarUrl'] : null,
            ],
        ]);
    }

    #[Route(path: '/reachability-check', name: 'api.frosh.tools.apps.reachability_check', methods: ['POST'])]
    public function checkReachability(): JsonResponse
    {
        return new JsonResponse($this->verifyAppUrl($this->getAppUrl()));
    }

    #[Route(path: '/shop-id/reset', name: 'api.frosh.tools.apps.shop_id_reset', defaults: ['_acl' => [FroshToolsPrivileges::APPS_UPDATE]], methods: ['POST'])]
    public function resetShopId(Request $request, Context $context): JsonResponse
    {
        $keepUserData = $request->request->getBoolean('keepUserData', false);

        $uninstalled = [];
        $failed = [];

        foreach ($this->getApps($context) as $app) {
            try {
                $context->scope(Context::SYSTEM_SCOPE, function (Context $systemContext) use ($app, $keepUserData): void {
                    $this->uninstallApp($app, $systemContext, $keepUserData);
                });
                $uninstalled[] = $app->getName();
            } catch (\Throwable) {
                // Uninstalling contacts the app server; unreachable apps must not block the reset.
                $failed[] = $app->getName();
            }
        }

        $this->shopIdProvider->deleteShopId();
        $shopId = (string) $this->shopIdProvider->getShopId();

        return new JsonResponse([
            'shopId' => $shopId,
            'uninstalledApps' => $uninstalled,
            'failedApps' => $failed,
        ]);
    }

    private function getAppUrl(): string
    {
        $appUrl = EnvironmentHelper::getVariable('APP_URL', '');

        return \is_string($appUrl) ? rtrim($appUrl, '/') : '';
    }

    /**
     * @return array{status: string, checkedAt: string|null, info: string|null, detailed: bool}
     */
    private function getCachedReachability(): array
    {
        if ($this->appUrlVerifier === null || !method_exists($this->appUrlVerifier, 'getCurrentState')) {
            return $this->reachabilityResult('unknown', null, null, false);
        }

        $state = $this->appUrlVerifier->getCurrentState();

        if (!\is_object($state)) {
            return $this->reachabilityResult('unknown', null, null, true);
        }

        // VerificationState exposes only public properties, so the cast keeps this readable
        // without referencing the class (it does not exist on Shopware 6.6).
        $data = (array) $state;

        $statusEnum = $data['status'] ?? null;
        $status = $statusEnum instanceof \UnitEnum ? strtolower($statusEnum->name) : 'unknown';

        $at = $data['at'] ?? null;
        $checkedAt = $at instanceof \DateTimeInterface ? $at->format(\DateTimeInterface::ATOM) : null;

        $info = \is_string($data['info'] ?? null) ? $data['info'] : null;

        return $this->reachabilityResult($status, $checkedAt, $info, true);
    }

    /**
     * @return array{status: string, checkedAt: string|null, info: string|null, detailed: bool}
     */
    private function verifyAppUrl(string $appUrl): array
    {
        if ($this->appUrlVerifier !== null && method_exists($this->appUrlVerifier, 'forceVerify')) {
            try {
                $shopId = $this->shopIdProvider->getShopId();
            } catch (\Throwable $e) {
                return $this->reachabilityResult('unknown', null, $e->getMessage(), true);
            }

            // getShopId() returns the ShopId object on Shopware >= 6.7 (a plain string on 6.6,
            // where the verifier service does not exist and this path is never reached).
            $this->appUrlVerifier->forceVerify($shopId, true);

            return $this->getCachedReachability();
        }

        // Shopware 6.6 has no token-based verifier, so fall back to a plain self-request over the public URL.
        return $this->basicReachabilityCheck($appUrl);
    }

    /**
     * @return array{status: string, checkedAt: string|null, info: string|null, detailed: bool}
     */
    private function basicReachabilityCheck(string $appUrl): array
    {
        $checkedAt = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

        if ($appUrl === '') {
            return $this->reachabilityResult('hard_fail', $checkedAt, 'APP_URL is not configured', false);
        }

        try {
            $response = $this->httpClient->request('GET', $appUrl . '/api/_info/version', [
                'timeout' => 3,
                'max_redirects' => 1,
                'headers' => ['Accept' => 'application/json'],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode === 200) {
                return $this->reachabilityResult('pass', $checkedAt, null, false);
            }

            return $this->reachabilityResult('hard_fail', $checkedAt, \sprintf('Unexpected HTTP status code "%d" from APP_URL', $statusCode), false);
        } catch (\Throwable $e) {
            return $this->reachabilityResult('hard_fail', $checkedAt, $e->getMessage(), false);
        }
    }

    /**
     * @return array{status: string, checkedAt: string|null, info: string|null, detailed: bool}
     */
    private function reachabilityResult(string $status, ?string $checkedAt, ?string $info, bool $detailed): array
    {
        return [
            'status' => $status,
            'checkedAt' => $checkedAt,
            'info' => $info,
            'detailed' => $detailed,
        ];
    }

    /**
     * Checks via a DAL filter whether the current admin user has a store token,
     * without calling the internal UserEntity::getStoreToken().
     */
    private function hasStoreToken(Context $context): bool
    {
        $source = $context->getSource();

        if (!$source instanceof AdminApiSource || $source->getUserId() === null) {
            return false;
        }

        $criteria = (new Criteria([$source->getUserId()]))
            ->addFilter(new NotEqualsFilter('storeToken', null));

        return $this->userRepository->searchIds($criteria, $context)->getTotal() > 0;
    }

    private function getShopId(): ?string
    {
        try {
            return (string) $this->shopIdProvider->getShopId();
        } catch (\Throwable) {
            // Shopware suggests a shop id change when the APP_URL changed while apps are registered.
            return null;
        }
    }

    /**
     * @return list<array{name: string, label: string|null, version: string, active: bool}>
     */
    private function getInstalledApps(Context $context): array
    {
        $apps = [];

        foreach ($this->getApps($context) as $app) {
            $apps[] = [
                'name' => $app->getName(),
                'label' => $app->getLabel(),
                'version' => $app->getVersion(),
                'active' => $app->isActive(),
            ];
        }

        return $apps;
    }

    /**
     * Read in system scope, as admin users browsing the tools usually lack app:read.
     *
     * @return iterable<AppEntity>
     */
    private function getApps(Context $context): iterable
    {
        return $context->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $systemContext) => $this->appRepository->search(new Criteria(), $systemContext)->getEntities(),
        );
    }

    private function uninstallApp(AppEntity $app, Context $context, bool $keepUserData): void
    {
        $payload = ['id' => $app->getId(), 'roleId' => $app->getAclRoleId()];

        // Shopware 6.7 renamed the lifecycle method from delete() to uninstall();
        // exactly one of the two guards always matches, depending on the Shopware version.
        // @phpstan-ignore function.alreadyNarrowedType
        if (method_exists($this->appLifecycle, 'uninstall')) {
            $this->appLifecycle->uninstall($app->getName(), $payload, $context, $keepUserData);

            return;
        }

        // @phpstan-ignore function.alreadyNarrowedType
        if (method_exists($this->appLifecycle, 'delete')) {
            $this->appLifecycle->delete($app->getName(), $payload, $context, $keepUserData);

            return;
        }

        throw new \RuntimeException('No app uninstall method available on the app lifecycle service');
    }
}
