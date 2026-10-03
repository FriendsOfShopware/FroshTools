<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Acl\FroshToolsPrivileges;
use Frosh\Tools\Components\Apps\AppUrlReachability;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\User\UserCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/_action/frosh-tools/apps', defaults: ['_routeScope' => ['api'], '_acl' => [FroshToolsPrivileges::APPS_READ]])]
class AppController extends AbstractController
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly ShopIdProvider $shopIdProvider,
        private readonly StoreClient $storeClient,
        private readonly EntityRepository $userRepository,
        private readonly EntityRepository $appRepository,
        #[Autowire(service: AppLifecycle::class)]
        private readonly AbstractAppLifecycle $appLifecycle,
        private readonly AppUrlReachability $appUrlReachability,
    ) {
    }

    #[Route(path: '/status', name: 'api.frosh.tools.apps.status', methods: ['GET'])]
    public function status(Context $context): JsonResponse
    {
        return new JsonResponse([
            'appUrl' => $this->getAppUrl(),
            'reachability' => $this->appUrlReachability->getCurrentState($this->getAppUrl()),
            'store' => ['loggedIn' => $this->hasStoreToken($context)],
            'hasShopId' => $this->getShopId() !== null,
            'apps' => $this->getInstalledApps($context),
        ]);
    }

    #[Route(path: '/shop-id', name: 'api.frosh.tools.apps.shop_id', methods: ['GET'])]
    public function shopId(Request $request): JsonResponse
    {
        $this->assertUserVerified($request);

        return new JsonResponse(['shopId' => $this->getShopId()], headers: ['Cache-Control' => 'no-store']);
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
        return new JsonResponse($this->appUrlReachability->check($this->getAppUrl()));
    }

    #[Route(path: '/reachability-probe', name: 'api.frosh.tools.apps.reachability_probe', defaults: ['auth_required' => false, '_acl' => []], methods: ['GET'])]
    public function reachabilityProbe(Request $request): JsonResponse
    {
        $challenge = $request->query->all()['challenge'] ?? null;
        $proof = \is_string($challenge) ? $this->appUrlReachability->createProof($challenge) : null;

        return new JsonResponse(
            $proof === null ? [] : ['proof' => $proof],
            $proof === null ? 400 : 200,
            ['Cache-Control' => 'no-store'],
        );
    }

    #[Route(path: '/shop-id/reset', name: 'api.frosh.tools.apps.shop_id_reset', defaults: ['_acl' => [FroshToolsPrivileges::APPS_UPDATE]], methods: ['POST'])]
    public function resetShopId(Request $request, Context $context): JsonResponse
    {
        $this->assertUserVerified($request);

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
        $this->shopIdProvider->getShopId();

        return new JsonResponse([
            'hasShopId' => true,
            'uninstalledApps' => $uninstalled,
            'failedApps' => $failed,
        ]);
    }

    private function assertUserVerified(Request $request): void
    {
        $scopes = $request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_SCOPES, []);
        if (!\is_array($scopes) || !\in_array(UserVerifiedScope::IDENTIFIER, $scopes, true)) {
            throw ApiException::invalidScopeAccessToken(UserVerifiedScope::IDENTIFIER);
        }
    }

    private function getAppUrl(): string
    {
        $appUrl = EnvironmentHelper::getVariable('APP_URL', '');

        return \is_string($appUrl) ? rtrim($appUrl, '/') : '';
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
            ->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('storeToken', null)]));

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
