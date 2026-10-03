<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Controller\AppController;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(AppController::class)]
class AppControllerTest extends IntegrationTestCase
{
    private AppController $controller;

    protected function setUp(): void
    {
        $this->controller = static::getContainer()->get(AppController::class);
    }

    public function testStatusReturnsAppOverview(): void
    {
        $status = $this->decodeResponse($this->controller->status(Context::createDefaultContext()));

        static::assertArrayHasKey('appUrl', $status);
        static::assertArrayHasKey('reachability', $status);
        static::assertContains($status['reachability']['status'], ['pass', 'soft_fail', 'hard_fail', 'unknown']);

        static::assertSame(['loggedIn' => false], $status['store']);

        static::assertTrue($status['hasShopId']);
        static::assertArrayNotHasKey('shopId', $status);
        static::assertIsArray($status['apps']);
    }

    public function testShopIdRequiresPasswordVerification(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_SCOPES, ['write']);
        $this->expectExceptionObject(ApiException::invalidScopeAccessToken(UserVerifiedScope::IDENTIFIER));

        $this->controller->shopId($request);
    }

    public function testShopIdRejectsRequestsWithoutScopes(): void
    {
        $this->expectExceptionObject(ApiException::invalidScopeAccessToken(UserVerifiedScope::IDENTIFIER));

        $this->controller->shopId(new Request());
    }

    public function testShopIdReturnsIdForVerifiedUserWithoutCaching(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_SCOPES, [UserVerifiedScope::IDENTIFIER]);
        $response = $this->controller->shopId($request);

        static::assertSame(
            (string) static::getContainer()->get(ShopIdProvider::class)->getShopId(),
            $this->decodeResponse($response)['shopId'],
        );
        static::assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testStoreUserInfoReturnsNullWithoutStoreToken(): void
    {
        $result = $this->decodeResponse($this->controller->storeUserInfo(Context::createDefaultContext()));

        static::assertSame(['user' => null], $result);
    }

    public function testStatusListsInstalledApps(): void
    {
        $this->createApp('FroshStatusTestApp');

        $status = $this->decodeResponse($this->controller->status(Context::createDefaultContext()));

        $names = array_column($status['apps'], 'name');
        static::assertContains('FroshStatusTestApp', $names);

        $app = $status['apps'][array_search('FroshStatusTestApp', $names, true)];
        static::assertSame('1.0.0', $app['version']);
        static::assertTrue($app['active']);
    }

    public function testCheckReachabilityReturnsResult(): void
    {
        $result = $this->decodeResponse($this->controller->checkReachability());

        static::assertContains($result['status'], ['pass', 'soft_fail', 'hard_fail', 'unknown']);
        static::assertArrayHasKey('checkedAt', $result);
        static::assertArrayHasKey('info', $result);
        static::assertArrayHasKey('detailed', $result);
    }

    public function testResetShopIdRejectsUnverifiedUserWithoutChangingIdOrUninstallingApps(): void
    {
        $this->createApp('FroshUnverifiedResetTestApp');
        $context = Context::createDefaultContext();
        $oldShopId = (string) static::getContainer()->get(ShopIdProvider::class)->getShopId();
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_SCOPES, ['write']);

        try {
            $this->controller->resetShopId($request, $context);
            static::fail('Reset must require password verification');
        } catch (ApiException $exception) {
            static::assertSame(
                ApiException::invalidScopeAccessToken(UserVerifiedScope::IDENTIFIER)->getErrorCode(),
                $exception->getErrorCode(),
            );
        }

        static::assertSame($oldShopId, (string) static::getContainer()->get(ShopIdProvider::class)->getShopId());
        $status = $this->decodeResponse($this->controller->status($context));
        static::assertContains('FroshUnverifiedResetTestApp', array_column($status['apps'], 'name'));
    }

    public function testResetShopIdRejectsRequestsWithoutScopes(): void
    {
        $this->expectExceptionObject(ApiException::invalidScopeAccessToken(UserVerifiedScope::IDENTIFIER));

        $this->controller->resetShopId(new Request(), Context::createDefaultContext());
    }

    public function testResetShopIdRegeneratesShopId(): void
    {
        $context = Context::createDefaultContext();
        $oldShopId = (string) static::getContainer()->get(ShopIdProvider::class)->getShopId();

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_SCOPES, [UserVerifiedScope::IDENTIFIER]);
        $result = $this->decodeResponse($this->controller->resetShopId($request, $context));

        $newShopId = (string) static::getContainer()->get(ShopIdProvider::class)->getShopId();
        static::assertNotSame($oldShopId, $newShopId);
        static::assertTrue($result['hasShopId']);
        static::assertArrayNotHasKey('shopId', $result);
        static::assertSame([], $result['failedApps']);

        $status = $this->decodeResponse($this->controller->status($context));
        static::assertTrue($status['hasShopId']);
        static::assertArrayNotHasKey('shopId', $status);
    }

    public function testResetShopIdUninstallsAllApps(): void
    {
        $this->createApp('FroshResetTestAppOne');
        $this->createApp('FroshResetTestAppTwo');

        $context = Context::createDefaultContext();

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_SCOPES, [UserVerifiedScope::IDENTIFIER]);
        $result = $this->decodeResponse($this->controller->resetShopId($request, $context));

        sort($result['uninstalledApps']);
        static::assertSame(['FroshResetTestAppOne', 'FroshResetTestAppTwo'], $result['uninstalledApps']);
        static::assertSame([], $result['failedApps']);

        $apps = static::getContainer()->get('app.repository')
            ->search(new Criteria(), $context)
            ->getEntities();

        static::assertCount(0, $apps, 'All apps should be uninstalled after the shop id reset');
    }

    private function createApp(string $name): void
    {
        static::getContainer()->get('app.repository')->create([
            [
                'id' => Uuid::randomHex(),
                'name' => $name,
                'path' => 'custom/apps/' . $name,
                'version' => '1.0.0',
                'label' => $name,
                'active' => true,
                'configurable' => false,
                'allowDisable' => true,
                'requestedPrivileges' => [],
                'integration' => [
                    'id' => Uuid::randomHex(),
                    'label' => $name,
                    'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
                    'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
                ],
                'aclRole' => [
                    'id' => Uuid::randomHex(),
                    'name' => $name,
                    'privileges' => [],
                ],
            ],
        ], Context::createDefaultContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
