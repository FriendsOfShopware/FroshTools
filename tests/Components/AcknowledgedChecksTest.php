<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components;

use Frosh\Tools\Components\AcknowledgedChecks;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AcknowledgedChecks::class)]
final class AcknowledgedChecksTest extends TestCase
{
    public function testHealthCheckBecomesInfoWithoutChangingTheComputedValues(): void
    {
        $collection = new HealthCollection();
        $collection->add(SettingsResult::error('php-version', 'PHP Version', '8.3.6', 'min 8.3.36'));
        $collection->add(SettingsResult::warning('untouched', 'Untouched', 'low', 'high'));

        AcknowledgedChecks::apply($collection, [
            'php-version' => '  Ubuntu 24.04 revision 8.3.6-0ubuntu0.24.04.13 contains the PHP 8.3.36 security fixes  ',
            'blank' => '   ',
            'missing-type' => 1,
        ]);

        $php = $this->healthResult($collection, 'php-version');
        static::assertSame(SettingsResult::INFO, $php->state);
        static::assertSame('8.3.6', $php->current);
        static::assertSame('min 8.3.36', $php->recommended);
        static::assertSame(
            'Ubuntu 24.04 revision 8.3.6-0ubuntu0.24.04.13 contains the PHP 8.3.36 security fixes',
            $php->acknowledgement,
        );

        $untouched = $this->healthResult($collection, 'untouched');
        static::assertSame(SettingsResult::WARNING, $untouched->state);
        static::assertSame('', $untouched->acknowledgement);
    }

    public function testSecurityFindingBecomesInfoAndDropsOutOfTheProblemCounts(): void
    {
        $collection = new SecurityCollection();
        $collection->add(SecurityFinding::high('runtime-php', SecurityFinding::CATEGORY_RUNTIME, 'PHP', '8.3.6', 'min 8.3.36'));
        $collection->add(SecurityFinding::critical('kept', SecurityFinding::CATEGORY_RUNTIME, 'Kept'));

        AcknowledgedChecks::apply($collection, [
            'runtime-php' => 'Confirmed by the operator',
        ]);

        $php = $this->securityFinding($collection, 'runtime-php');
        static::assertSame(SecurityFinding::SEVERITY_INFO, $php->severity);
        static::assertSame('8.3.6', $php->current);
        static::assertSame('min 8.3.36', $php->recommended);
        static::assertSame('Confirmed by the operator', $php->acknowledgement);

        static::assertSame([
            SecurityFinding::SEVERITY_CRITICAL => 1,
            SecurityFinding::SEVERITY_HIGH => 0,
            SecurityFinding::SEVERITY_MEDIUM => 0,
            SecurityFinding::SEVERITY_LOW => 0,
            SecurityFinding::SEVERITY_UNKNOWN => 0,
            SecurityFinding::SEVERITY_OK => 0,
        ], $collection->countBySeverity());
    }

    private function healthResult(HealthCollection $collection, string $id): SettingsResult
    {
        foreach ($collection as $result) {
            if ($result->id === $id) {
                return $result;
            }
        }

        static::fail(\sprintf('Missing health result "%s"', $id));
    }

    private function securityFinding(SecurityCollection $collection, string $id): SecurityFinding
    {
        foreach ($collection as $finding) {
            if ($finding->id === $id) {
                return $finding;
            }
        }

        static::fail(\sprintf('Missing security finding "%s"', $id));
    }
}
