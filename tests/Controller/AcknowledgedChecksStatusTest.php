<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Components\Sbom\CycloneDxSbomGenerator;
use Frosh\Tools\Components\Security\AdminAccountService;
use Frosh\Tools\Components\Security\Checker\SecurityCheckerInterface;
use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;
use Frosh\Tools\Controller\HealthController;
use Frosh\Tools\Controller\SecurityController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(HealthController::class)]
#[CoversClass(SecurityController::class)]
final class AcknowledgedChecksStatusTest extends TestCase
{
    private const REASON = 'Ubuntu 24.04 revision 8.3.6-0ubuntu0.24.04.13 contains the PHP 8.3.36 security fixes';

    public function testHealthAndPerformanceKeepAcknowledgedRowsAsInfo(): void
    {
        $health = new class implements CheckerInterface {
            public function collect(HealthCollection $collection): void
            {
                $collection->add(SettingsResult::error('php-version', 'PHP Version', '8.3.6', 'min 8.3.36'));
                $collection->add(SettingsResult::warning('hidden-health', 'Hidden', 'on', 'off'));
            }
        };
        $performance = new class implements CheckerInterface {
            public function collect(HealthCollection $collection): void
            {
                $collection->add(SettingsResult::warning('php-settings', 'PHP settings', 'low', 'high'));
                $collection->add(SettingsResult::error('hidden-performance', 'Hidden', 'bad', 'good'));
            }
        };

        $controller = new HealthController(
            [$health],
            [$performance],
            [],
            new ArrayAdapter(),
            ['hidden-health', 'hidden-performance'],
            [
                'php-version' => self::REASON,
                'php-settings' => 'Operator confirmed this setting',
                'hidden-health' => 'Disabled wins',
                'hidden-performance' => 'Disabled wins',
            ],
        );

        $healthEntries = $this->decodeList($controller->status());
        static::assertSame(['php-version'], array_column($healthEntries, 'id'));
        static::assertSame(SettingsResult::INFO, $healthEntries[0]['state']);
        static::assertSame('8.3.6', $healthEntries[0]['current']);
        static::assertSame('min 8.3.36', $healthEntries[0]['recommended']);
        static::assertSame(self::REASON, $healthEntries[0]['acknowledgement']);
        static::assertSame(0, $this->countState($healthEntries, SettingsResult::ERROR));

        $performanceEntries = $this->decodeList($controller->performanceStatus());
        static::assertSame(['php-settings'], array_column($performanceEntries, 'id'));
        static::assertSame(SettingsResult::INFO, $performanceEntries[0]['state']);
        static::assertSame('low', $performanceEntries[0]['current']);
        static::assertSame('high', $performanceEntries[0]['recommended']);
        static::assertSame('Operator confirmed this setting', $performanceEntries[0]['acknowledgement']);
        static::assertSame(0, $this->countState($performanceEntries, SettingsResult::WARNING));
    }

    public function testDataIntegrityUsesTheSameAcknowledgementRule(): void
    {
        $checker = new class implements CheckerInterface {
            public function collect(HealthCollection $collection): void
            {
                $collection->add(SettingsResult::error('duplicate-emails', 'Duplicate emails', '2', '0'));
                $collection->add(SettingsResult::ok('hidden-integrity', 'Hidden', '0', '0'));
            }
        };

        $controller = new HealthController(
            [],
            [],
            [$checker],
            new ArrayAdapter(),
            ['hidden-integrity'],
            ['duplicate-emails' => 'Reviewed and accepted'],
        );

        $entries = $this->decodeList($controller->dataIntegrityStatus(new Request()));
        static::assertSame(['duplicate-emails'], array_column($entries, 'id'));
        static::assertSame(SettingsResult::INFO, $entries[0]['state']);
        static::assertSame('2', $entries[0]['current']);
        static::assertSame('0', $entries[0]['recommended']);
        static::assertSame('Reviewed and accepted', $entries[0]['acknowledgement']);
    }

    public function testSecurityAcknowledgementDoesNotCountAndDisabledChecksWin(): void
    {
        $checker = new class implements SecurityCheckerInterface {
            public function collect(SecurityCollection $collection): void
            {
                $collection->add(SecurityFinding::high('visible', SecurityFinding::CATEGORY_RUNTIME, 'Visible', '8.3.6', 'min 8.3.36'));
                $collection->add(SecurityFinding::medium('hidden', SecurityFinding::CATEGORY_RUNTIME, 'Hidden', 'on', 'off'));
                $collection->add(SecurityFinding::critical('kept', SecurityFinding::CATEGORY_CONFIGURATION, 'Kept'));
            }
        };

        $controller = new SecurityController(
            [$checker],
            ['hidden'],
            $this->createStub(CycloneDxSbomGenerator::class),
            $this->createStub(AdminAccountService::class),
            $this->createStub(ClockInterface::class),
            [
                'visible' => self::REASON,
                'hidden' => 'Disabled wins',
            ],
        );

        $payload = json_decode((string) $controller->status()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($payload);
        static::assertSame(1, $payload['summary']['critical']);
        static::assertSame(0, $payload['summary']['high']);
        static::assertSame(0, $payload['summary']['medium']);

        $findings = $payload['findings'];
        static::assertSame(['kept', 'visible'], array_column($findings, 'id'));
        static::assertSame(SecurityFinding::SEVERITY_INFO, $findings[1]['severity']);
        static::assertSame('8.3.6', $findings[1]['current']);
        static::assertSame('min 8.3.36', $findings[1]['recommended']);
        static::assertSame(self::REASON, $findings[1]['acknowledgement']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeList(\Symfony\Component\HttpFoundation\JsonResponse $response): array
    {
        $entries = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($entries);

        return $entries;
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private function countState(array $entries, string $state): int
    {
        return \count(array_filter(
            $entries,
            static fn (array $entry): bool => $entry['state'] === $state,
        ));
    }
}
