<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\AffectedEntities;
use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\AffectedEntitiesProviderInterface;
use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\DataIntegrityCheckerInterface;
use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\InvalidRuleChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Controller\HealthController;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
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

    public function testDataIntegrityAffectedListsTheRecordsOfACheck(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('UPDATE `rule` SET invalid = 0');
        $ruleIds = [];
        for ($i = 0; $i < 3; ++$i) {
            $ruleIds[] = Uuid::randomHex();
            $connection->insert('rule', [
                'id' => Uuid::fromHexToBytes($ruleIds[$i]),
                'name' => 'Frosh data integrity check rule',
                'priority' => 1,
                'invalid' => 1,
                'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
        sort($ruleIds);

        $firstPage = $this->decodeAffected($this->controller->dataIntegrityAffected('rule-invalid', new Request(['limit' => '2']), Context::createDefaultContext()));
        $secondPage = $this->decodeAffected($this->controller->dataIntegrityAffected('rule-invalid', new Request(['limit' => '2', 'page' => '2']), Context::createDefaultContext()));

        static::assertSame(['entity' => 'rule', 'total' => 3, 'ids' => \array_slice($ruleIds, 0, 2), 'page' => 1, 'limit' => 2], $firstPage);
        static::assertSame(['entity' => 'rule', 'total' => 3, 'ids' => [$ruleIds[2]], 'page' => 2, 'limit' => 2], $secondPage);
    }

    public function testDataIntegrityAffectedClampsPaging(): void
    {
        $affected = $this->decodeAffected($this->controller->dataIntegrityAffected('rule-invalid', new Request(['limit' => '1000', 'page' => '-3']), Context::createDefaultContext()));

        static::assertSame(100, $affected['limit']);
        static::assertSame(1, $affected['page']);
    }

    public function testDataIntegrityAffectedReturnsNotFoundForUnknownChecks(): void
    {
        $response = $this->controller->dataIntegrityAffected('unknown-check', new Request(), Context::createDefaultContext());

        static::assertSame(404, $response->getStatusCode());
    }

    public function testDataIntegrityAffectedReturnsAnEmptyPageForHugePageNumbers(): void
    {
        $affected = $this->decodeAffected($this->controller->dataIntegrityAffected('rule-invalid', new Request(['limit' => '100', 'page' => (string) \PHP_INT_MAX]), Context::createDefaultContext()));

        static::assertSame([], $affected['ids']);
    }

    public function testDataIntegrityAffectedReturnsNotFoundForIgnoredChecks(): void
    {
        $controller = new HealthController(
            [],
            [],
            [static::getContainer()->get(InvalidRuleChecker::class)],
            static::getContainer()->get('cache.object'),
            ['rule-invalid'],
        );

        $response = $controller->dataIntegrityAffected('rule-invalid', new Request(), Context::createDefaultContext());

        static::assertSame(404, $response->getStatusCode());
    }

    public function testDataIntegrityAffectedRequiresReadPermissionForTheEntity(): void
    {
        $response = $this->controller->dataIntegrityAffected('rule-invalid', new Request(), $this->adminContext([]));

        static::assertSame(403, $response->getStatusCode());
        static::assertSame(['error' => 'Missing privilege "rule:read"'], json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testDataIntegrityAffectedChecksPermissionBeforeQueryingTheRecords(): void
    {
        $checker = new class implements AffectedEntitiesProviderInterface, DataIntegrityCheckerInterface, CheckerInterface {
            public bool $queried = false;

            public function getId(): string
            {
                return 'scan-test';
            }

            public function getAffectedEntity(): string
            {
                return 'customer';
            }

            public function getAffectedEntities(int $limit, int $offset): AffectedEntities
            {
                $this->queried = true;

                return new AffectedEntities('customer', 0, []);
            }

            public function collect(HealthCollection $collection): void
            {
            }
        };

        $controller = new HealthController(
            [],
            [],
            [$checker],
            static::getContainer()->get('cache.object'),
            [],
        );

        $response = $controller->dataIntegrityAffected('scan-test', new Request(), $this->adminContext([]));

        static::assertSame(403, $response->getStatusCode());
        static::assertFalse($checker->queried);
    }

    public function testDataIntegrityAffectedRejectsRecordsOfAnotherEntityThanDeclared(): void
    {
        $checker = new class implements AffectedEntitiesProviderInterface, DataIntegrityCheckerInterface, CheckerInterface {
            public function getId(): string
            {
                return 'mismatch-test';
            }

            public function getAffectedEntity(): string
            {
                return 'rule';
            }

            public function getAffectedEntities(int $limit, int $offset): AffectedEntities
            {
                return new AffectedEntities('customer', 1, ['0123456789abcdef0123456789abcdef']);
            }

            public function collect(HealthCollection $collection): void
            {
            }
        };

        $controller = new HealthController(
            [],
            [],
            [$checker],
            static::getContainer()->get('cache.object'),
            [],
        );

        $this->expectException(\LogicException::class);

        $controller->dataIntegrityAffected('mismatch-test', new Request(), $this->adminContext(['rule:read']));
    }

    public function testDataIntegrityAffectedListsTheRecordsWithReadPermission(): void
    {
        $response = $this->controller->dataIntegrityAffected('rule-invalid', new Request(), $this->adminContext(['rule:read']));

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('rule', $this->decodeAffected($response)['entity']);
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

    /**
     * @param list<string> $permissions
     */
    private function adminContext(array $permissions): Context
    {
        $source = new AdminApiSource(null);
        $source->setPermissions($permissions);

        return new Context($source);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeAffected(JsonResponse $response): array
    {
        static::assertSame(200, $response->getStatusCode());

        $affected = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($affected);

        return $affected;
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
