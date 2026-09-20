<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Checker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\Checker\SoftDeletedIntegrationChecker;
use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Symfony\Component\Clock\MockClock;

#[CoversClass(SoftDeletedIntegrationChecker::class)]
class SoftDeletedIntegrationCheckerTest extends TestCase
{
    public function testReportsMediumWhenStaleSoftDeletedIntegrationsExist(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('fetchOne')
            ->with(
                static::stringContains('FROM `integration`'),
                ['threshold' => (new \DateTimeImmutable('2023-01-09 12:00:00', new \DateTimeZone('UTC')))->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
            )
            ->willReturn(3);

        $finding = $this->collect($connection, '2023-01-10 12:00:00');

        static::assertSame(SoftDeletedIntegrationChecker::ID, $finding->id);
        static::assertSame(SecurityFinding::SEVERITY_MEDIUM, $finding->severity);
        static::assertSame(SecurityFinding::CATEGORY_RUNTIME, $finding->category);
        static::assertSame('Soft-deleted integrations', $finding->title);
        static::assertSame('3 integration(s) soft-deleted for more than 1 day(s)', $finding->current);
        static::assertStringContainsString('app_delete', $finding->recommended);
    }

    public function testReportsOkWhenNoStaleSoftDeletedIntegrationsExist(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('fetchOne')
            ->willReturn(0);

        $finding = $this->collect($connection, '2023-01-10 12:00:00');

        static::assertSame(SoftDeletedIntegrationChecker::ID, $finding->id);
        static::assertSame(SecurityFinding::SEVERITY_OK, $finding->severity);
        static::assertSame('none pending hard delete', $finding->current);
    }

    public function testReportsUnknownWhenQueryFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('fetchOne')
            ->willThrowException(new \RuntimeException('connection lost'));

        $finding = $this->collect($connection, '2023-01-10 12:00:00');

        static::assertSame(SoftDeletedIntegrationChecker::ID, $finding->id);
        static::assertSame(SecurityFinding::SEVERITY_UNKNOWN, $finding->severity);
        static::assertStringContainsString('connection lost', $finding->recommended);
    }

    private function collect(Connection $connection, string $now): SecurityFinding
    {
        $collection = new SecurityCollection();
        (new SoftDeletedIntegrationChecker($connection, new MockClock($now)))->collect($collection);

        static::assertCount(1, $collection);

        /** @var SecurityFinding $finding */
        $finding = $collection->first();

        return $finding;
    }
}
