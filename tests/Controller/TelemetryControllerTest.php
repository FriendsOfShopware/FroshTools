<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Components\Telemetry\PostHogClient;
use Frosh\Tools\Components\Telemetry\TelemetryCollector;
use Frosh\Tools\Components\Telemetry\TelemetryService;
use Frosh\Tools\Controller\TelemetryController;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(TelemetryController::class)]
class TelemetryControllerTest extends IntegrationTestCase
{
    private TelemetryController $controller;
    private SystemConfigService $configService;

    protected function setUp(): void
    {
        $this->controller = static::getContainer()->get(TelemetryController::class);
        $this->configService = static::getContainer()->get(SystemConfigService::class);
        $this->configService->set(TelemetryService::CONFIG_ENABLED, false);
        $this->configService->delete(TelemetryService::CONFIG_INSTANCE_ID);
    }

    public function testStatusShowsOptInStateAndPayloadPreview(): void
    {
        $status = $this->decodeResponse($this->controller->status());

        static::assertFalse($status['enabled']);
        static::assertFalse($status['posthogConfigured'], 'Placeholder project key must count as not configured');
        static::assertSame(PostHogClient::DEFAULT_HOST, $status['host']);
        static::assertSame(PostHogClient::EVENT, $status['event']);
        static::assertSame(TelemetryCollector::SCHEMA_VERSION, $status['schemaVersion']);
        static::assertNull($status['instanceId'], 'Viewing the status must not create an installation ID');
        static::assertIsArray($status['payload']);
        static::assertSame(TelemetryCollector::SCHEMA_VERSION, $status['payload']['schema_version']);
        static::assertArrayHasKey('shopware.version', $status['payload']);

        // The preview is read-only: still no ID persisted afterwards.
        static::assertSame('', $this->configService->getString(TelemetryService::CONFIG_INSTANCE_ID));
    }

    public function testSetOptInPersistsThePluginConfiguration(): void
    {
        $status = $this->decodeResponse($this->controller->setOptIn($this->jsonRequest(['enabled' => true])));
        static::assertTrue($status['enabled']);
        static::assertTrue($this->configService->getBool(TelemetryService::CONFIG_ENABLED));

        $status = $this->decodeResponse($this->controller->setOptIn($this->jsonRequest(['enabled' => false])));
        static::assertFalse($status['enabled']);
        static::assertFalse($this->configService->getBool(TelemetryService::CONFIG_ENABLED));
    }

    public function testSetOptInRejectsNonBooleanValue(): void
    {
        $response = $this->controller->setOptIn($this->jsonRequest(['enabled' => 'yes']));

        static::assertSame(JsonResponse::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertFalse($this->configService->getBool(TelemetryService::CONFIG_ENABLED));
    }

    public function testSendNowRequiresOptIn(): void
    {
        $result = $this->decodeResponse($this->controller->sendNow());

        static::assertFalse($result['sent']);
        static::assertSame('not-enabled', $result['reason']);
    }

    public function testSendNowWithoutProjectKeyReportsNotConfigured(): void
    {
        $this->configService->set(TelemetryService::CONFIG_ENABLED, true);

        $result = $this->decodeResponse($this->controller->sendNow());

        static::assertFalse($result['sent']);
        static::assertSame('not-configured', $result['reason']);
    }

    public function testResetInstanceIdDeletesThePersistedId(): void
    {
        $this->configService->set(TelemetryService::CONFIG_INSTANCE_ID, 'anonymous-test-id');
        static::assertSame('anonymous-test-id', $this->decodeResponse($this->controller->status())['instanceId']);

        $status = $this->decodeResponse($this->controller->resetInstanceId());

        static::assertNull($status['instanceId']);
        static::assertSame('', $this->configService->getString(TelemetryService::CONFIG_INSTANCE_ID));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function jsonRequest(array $body): Request
    {
        return new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($body, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
