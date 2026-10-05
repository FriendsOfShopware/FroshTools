<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Components\Security\Activity\ActivityStore;
use Frosh\Tools\Controller\SecurityActivityController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SecurityActivityController::class)]
class SecurityActivityControllerTest extends TestCase
{
    public function testReturnsFilteredActivityWithoutCaching(): void
    {
        $store = $this->createMock(ActivityStore::class);
        $store->expects($this->once())->method('search')->with(2, 10, 'user:', 'admin', new \DateTimeImmutable('2026-10-01 UTC'), new \DateTimeImmutable('2026-10-04 UTC'))->willReturn(['entries' => [], 'total' => 0]);
        $response = (new SecurityActivityController($store))->activity(new Request(['page' => 2, 'limit' => 10, 'action' => ' user: ', 'actor' => ' admin ', 'from' => '2026-10-01', 'to' => '2026-10-04']));
        static::assertSame(['entries' => [], 'total' => 0], json_decode((string) $response->getContent(), true));
        static::assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testPassesIpAndUserFilters(): void
    {
        $userId = str_repeat('a', 32);
        $store = $this->createMock(ActivityStore::class);
        $store->expects($this->once())->method('search')->with(1, 25, '', '', null, null, true, '2001:db8::1', $userId)->willReturn(['entries' => [], 'total' => 0]);
        (new SecurityActivityController($store))->activity(new Request(['exact' => '1', 'clientIp' => ' 2001:db8::1 ', 'userId' => $userId]));
    }

    public function testOptionsAreReadOnlyAndBoundToKnownFields(): void
    {
        $store = $this->createMock(ActivityStore::class);
        $store->expects($this->once())->method('filterOptions')->with('actor', 'ali')->willReturn(['values' => ['alice'], 'hasMore' => false]);
        $response = (new SecurityActivityController($store))->options(new Request(['field' => 'actor', 'term' => ' ali ']));
        static::assertSame(['values' => ['alice'], 'hasMore' => false], json_decode((string) $response->getContent(), true));
        static::assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function testOptionsRejectUnknownFields(): void
    {
        $store = $this->createMock(ActivityStore::class);
        $store->expects($this->never())->method('filterOptions');
        $this->expectException(BadRequestHttpException::class);
        (new SecurityActivityController($store))->options(new Request(['field' => 'password']));
    }

    #[DataProvider('invalidFilters')]
    public function testRejectsInvalidFilters(array $query): void
    {
        $store = $this->createMock(ActivityStore::class);
        $store->expects($this->never())->method('search');
        $this->expectException(BadRequestHttpException::class);
        (new SecurityActivityController($store))->activity(new Request($query));
    }

    public static function invalidFilters(): iterable
    {
        yield 'missing subject type' => [['subjectId' => str_repeat('a', 32)]];
        yield 'missing subject ID' => [['subjectType' => 'users']];
        yield 'unknown subject type' => [['subjectType' => 'products', 'subjectId' => str_repeat('a', 32)]];
        yield 'invalid subject ID' => [['subjectType' => 'users', 'subjectId' => 'alice']];
        yield 'invalid IP' => [['clientIp' => '192.0.2.999']];
        yield 'IP prefix is not an address' => [['clientIp' => '192.0.2.']];
        yield 'invalid user ID' => [['userId' => 'admin']];
        yield 'negative page' => [['page' => -1]];
        yield 'excessive page size' => [['limit' => 101]];
        yield 'malformed date with null byte' => [['from' => "2026-10-04\0"]];
        yield 'impossible date' => [['from' => '2026-02-30']];
        yield 'reversed range' => [['from' => '2026-10-04', 'to' => '2026-10-01']];
        yield 'unbounded search' => [['actor' => str_repeat('a', 256)]];
    }
}
