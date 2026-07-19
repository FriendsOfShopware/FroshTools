<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Controller\ShopmonController;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ShopmonController::class)]
class ShopmonControllerTest extends IntegrationTestCase
{
    private const INTEGRATION_ID = 'c2f0a1d4b6e84f309a5d7c1e8b2f4a60';
    private const CONFIG_KEY = 'FroshTools.config.shopmonIntegrationData';

    private ShopmonController $controller;

    protected function setUp(): void
    {
        $this->controller = static::getContainer()->get(ShopmonController::class);
    }

    public function testStatusReturnsNotConfigured(): void
    {
        $context = Context::createDefaultContext();

        // Ensure a clean slate even if the installation was configured before
        $this->controller->remove($context);

        $status = $this->decodeResponse($this->controller->status($context));

        static::assertSame(['configured' => false], $status);
    }

    public function testSetupCreatesIntegrationAndConfiguration(): void
    {
        $context = Context::createDefaultContext();

        $status = $this->decodeResponse($this->controller->setup($context));

        static::assertTrue($status['configured']);
        static::assertNotEmpty($status['clientId']);
        static::assertNotEmpty($status['clientSecret']);

        $integration = static::getContainer()->get('integration.repository')
            ->search(new Criteria([self::INTEGRATION_ID]), $context)
            ->getEntities()
            ->first();

        static::assertNotNull($integration, 'Setup should create the shopmon integration');

        $configValue = static::getContainer()->get(SystemConfigService::class)->getString(self::CONFIG_KEY);
        static::assertNotSame('', $configValue, 'Setup should persist the integration data in the system config');
    }

    public function testSetupIsIdempotent(): void
    {
        $context = Context::createDefaultContext();

        $first = $this->decodeResponse($this->controller->setup($context));
        $second = $this->decodeResponse($this->controller->setup($context));

        static::assertTrue($first['configured']);
        static::assertTrue($second['configured']);

        $result = static::getContainer()->get('integration.repository')
            ->search(new Criteria([self::INTEGRATION_ID]), $context);

        static::assertSame(1, $result->getTotal(), 'Calling setup twice must not duplicate the integration');
    }

    public function testRemoveDeletesIntegrationAndConfiguration(): void
    {
        $context = Context::createDefaultContext();

        $this->controller->setup($context);

        $response = $this->controller->remove($context);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $status = $this->decodeResponse($this->controller->status($context));
        static::assertSame(['configured' => false], $status);

        $integration = static::getContainer()->get('integration.repository')
            ->search(new Criteria([self::INTEGRATION_ID]), $context)
            ->getEntities()
            ->first();

        static::assertNull($integration, 'Remove should delete the shopmon integration');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
