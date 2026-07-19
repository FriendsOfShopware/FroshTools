<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Controller\QueueController;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(QueueController::class)]
class QueueControllerIntegrationTest extends IntegrationTestCase
{
    private QueueController $controller;

    protected function setUp(): void
    {
        $this->controller = static::getContainer()->get(QueueController::class);
    }

    public function testTransportsReturnsConfiguredTransports(): void
    {
        $response = $this->controller->transports();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $transports = $this->decodeResponse($response);

        static::assertNotEmpty($transports, 'At least one messenger transport should be configured');

        $names = array_column($transports, 'name');
        static::assertContains('async', $names, 'The default "async" transport should be present');

        foreach ($transports as $transport) {
            foreach (['name', 'type', 'size', 'oldestMessageAgeSeconds', 'workerLastSeenSeconds', 'browsable', 'requeuesOnBrowse', 'removable', 'retryable', 'purgeable'] as $key) {
                static::assertArrayHasKey($key, $transport);
            }

            static::assertIsString($transport['name']);
            static::assertIsString($transport['type']);
        }
    }

    public function testListReturnsQueueList(): void
    {
        $response = $this->controller->list();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $list = $this->decodeResponse($response);

        foreach ($list as $entry) {
            static::assertArrayHasKey('name', $entry);
            static::assertArrayHasKey('size', $entry);
        }
    }

    public function testMessagesReturnsMessageOverviewForTransport(): void
    {
        $response = $this->controller->messages('async', new Request(['limit' => 10]));

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = $this->decodeResponse($response);

        foreach (['transport', 'type', 'size', 'messages'] as $key) {
            static::assertArrayHasKey($key, $data);
        }

        static::assertSame('async', $data['transport']);
        static::assertIsArray($data['messages']);
    }

    public function testMessagesWithUnknownTransportReturns404(): void
    {
        $response = $this->controller->messages('does-not-exist', new Request(['limit' => 10]));

        $this->assertUnknownTransportResponse($response);
    }

    public function testDeleteMessageWithUnknownTransportReturns404(): void
    {
        $response = $this->controller->deleteMessage('does-not-exist', '1');

        $this->assertUnknownTransportResponse($response);
    }

    public function testRetryMessageWithUnknownTransportReturns404(): void
    {
        $response = $this->controller->retryMessage('does-not-exist', '1');

        $this->assertUnknownTransportResponse($response);
    }

    private function assertUnknownTransportResponse(JsonResponse $response): void
    {
        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $body = $this->decodeResponse($response);
        static::assertArrayHasKey('error', $body);
    }

    /**
     * @return array<mixed>
     */
    private function decodeResponse(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
