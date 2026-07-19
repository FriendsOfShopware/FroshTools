<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Controller\HealthController;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

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
    }

    public function testPerformanceStatusReturnsValidResultList(): void
    {
        $response = $this->controller->performanceStatus();

        static::assertSame(200, $response->getStatusCode());

        $this->decodeAndAssertResultList($response);
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
