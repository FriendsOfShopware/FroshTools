<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Controller\HealthController;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(HealthController::class)]
class HealthControllerTest extends IntegrationTestCase
{
    private const VALID_STATES = [
        SettingsResult::GREEN,
        SettingsResult::WARNING,
        SettingsResult::ERROR,
        SettingsResult::INFO,
    ];

    private HealthController $controller;

    protected function setUp(): void
    {
        $this->controller = static::getContainer()->get(HealthController::class);
        $this->deleteCachedDataIntegrityStatus();
    }

    protected function tearDown(): void
    {
        $this->deleteCachedDataIntegrityStatus();
    }

    public function testStatusReturnsCollectionWithKnownCheckers(): void
    {
        $response = $this->controller->status();

        static::assertSame(200, $response->getStatusCode());

        $entries = $this->decodeAndAssertResultList($response);

        $ids = array_column($entries, 'id');
        static::assertContains('queue', $ids);
        static::assertContains('scheduled_task', $ids);
        static::assertContains('system-time', $ids);
        static::assertContains('shopware-installer', $ids);
    }

    public function testPerformanceStatusReturnsValidResultList(): void
    {
        $response = $this->controller->performanceStatus();

        static::assertSame(200, $response->getStatusCode());

        $this->decodeAndAssertResultList($response);
    }

    public function testDataIntegrityStatusReturnsDataIntegrityChecks(): void
    {
        $response = $this->controller->dataIntegrityStatus(new Request());

        static::assertSame(200, $response->getStatusCode());

        $entries = $this->decodeAndAssertResultList($response);

        $ids = array_column($entries, 'id');
        static::assertContains('product-canonical-other-family', $ids);
        static::assertContains('duplicate-delivery-times', $ids);
        static::assertContains('duplicate-customer-emails', $ids);
        static::assertNotContains('queue', $ids);
    }

    public function testDataIntegrityStatusIsCached(): void
    {
        $first = $this->dataIntegrityCurrent('rule-invalid', new Request());
        $this->invalidateRule();

        static::assertSame($first, $this->dataIntegrityCurrent('rule-invalid', new Request()));
    }

    public function testDataIntegrityStatusRefreshBypassesCache(): void
    {
        $first = $this->dataIntegrityCurrent('rule-invalid', new Request());
        $this->invalidateRule();

        static::assertSame(
            (string) ((int) $first + 1),
            $this->dataIntegrityCurrent('rule-invalid', new Request(['refresh' => '1'])),
        );
        static::assertSame(
            (string) ((int) $first + 1),
            $this->dataIntegrityCurrent('rule-invalid', new Request()),
        );
    }

    public function testPingStatusReturnsCachedResponse(): void
    {
        $cachePool = static::getContainer()->get('cache.object');
        static::assertInstanceOf(CacheItemPoolInterface::class, $cachePool);
        $cachePool->deleteItem('health-ping');

        $first = $this->controller->pingStatus();
        $second = $this->controller->pingStatus();

        static::assertSame(200, $first->getStatusCode());
        static::assertSame(200, $second->getStatusCode());
        static::assertSame($first->getContent(), $second->getContent());
    }

    private function dataIntegrityCurrent(string $id, Request $request): string
    {
        $entries = $this->decodeAndAssertResultList($this->controller->dataIntegrityStatus($request));
        $entry = array_values(array_filter($entries, static fn (array $entry): bool => $entry['id'] === $id))[0] ?? null;

        static::assertIsArray($entry);
        static::assertIsString($entry['current']);

        return $entry['current'];
    }

    private function invalidateRule(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('UPDATE rule SET invalid = 1 WHERE invalid = 0 LIMIT 1');
    }

    private function deleteCachedDataIntegrityStatus(): void
    {
        $cachePool = static::getContainer()->get('cache.object');
        static::assertInstanceOf(CacheItemPoolInterface::class, $cachePool);
        $cachePool->deleteItem('frosh-tools-data-integrity-status');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeAndAssertResultList(JsonResponse $response): array
    {
        $entries = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        static::assertIsArray($entries);
        static::assertNotEmpty($entries);
        static::assertSame(range(0, \count($entries) - 1), array_keys($entries), 'Response should be a JSON list');

        foreach ($entries as $entry) {
            static::assertIsArray($entry);
            static::assertArrayHasKey('id', $entry);
            static::assertArrayHasKey('state', $entry);
            static::assertContains($entry['state'], self::VALID_STATES);
        }

        return $entries;
    }
}
