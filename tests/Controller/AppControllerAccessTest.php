<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Controller\AppController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

#[CoversClass(AppController::class)]
class AppControllerAccessTest extends TestCase
{
    #[DataProvider('unverifiedScopes')]
    public function testUnverifiedRequestsAreRejectedBeforeAccessingShopId(mixed $scopes): void
    {
        $controller = (new \ReflectionClass(AppController::class))->newInstanceWithoutConstructor();
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_SCOPES, $scopes);

        try {
            $controller->shopId($request);
            static::fail('An unverified request must not access the shop ID.');
        } catch (HttpExceptionInterface $exception) {
            static::assertSame(403, $exception->getStatusCode());
        }
    }

    public static function unverifiedScopes(): iterable
    {
        yield 'missing scope' => [[]];
        yield 'unrelated scope' => [['write']];
        yield 'malformed scopes' => ['user-verified'];
    }
}
