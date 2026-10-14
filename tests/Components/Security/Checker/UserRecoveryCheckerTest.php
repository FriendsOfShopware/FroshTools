<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Checker;

use Frosh\Tools\Components\Security\Checker\UserRecoveryChecker;
use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

#[CoversClass(UserRecoveryChecker::class)]
class UserRecoveryCheckerTest extends TestCase
{
    public function testReportsOkWhenDisabledViaFroshTools(): void
    {
        $finding = $this->collect(false, ['shopware.api.user_recovery.enabled' => true]);

        static::assertSame(UserRecoveryChecker::ID, $finding->id);
        static::assertSame(SecurityFinding::SEVERITY_OK, $finding->severity);
        static::assertSame(SecurityFinding::CATEGORY_CONFIGURATION, $finding->category);
        static::assertStringContainsString('FroshTools', $finding->current);
    }

    public function testReportsOkWhenDisabledViaShopwareConfig(): void
    {
        $finding = $this->collect(true, ['shopware.api.user_recovery.enabled' => false]);

        static::assertSame(UserRecoveryChecker::ID, $finding->id);
        static::assertSame(SecurityFinding::SEVERITY_OK, $finding->severity);
        static::assertStringContainsString('Shopware config', $finding->current);
    }

    public function testReportsLowWhenEnabled(): void
    {
        $finding = $this->collect(true, ['shopware.api.user_recovery.enabled' => true]);

        static::assertSame(UserRecoveryChecker::ID, $finding->id);
        static::assertSame(SecurityFinding::SEVERITY_LOW, $finding->severity);
        static::assertSame('enabled', $finding->current);
        static::assertStringContainsString('frosh_tools.user_recovery.enabled', $finding->recommended);
    }

    public function testReportsLowWhenShopwareOptionDoesNotExist(): void
    {
        // Shopware versions without core support (shopware/shopware#21637) have no such parameter.
        $finding = $this->collect(true, []);

        static::assertSame(SecurityFinding::SEVERITY_LOW, $finding->severity);
        static::assertSame('enabled', $finding->current);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function collect(bool $froshToolsEnabled, array $parameters): SecurityFinding
    {
        $collection = new SecurityCollection();
        (new UserRecoveryChecker($froshToolsEnabled, new ParameterBag($parameters)))->collect($collection);

        static::assertCount(1, $collection);

        /** @var SecurityFinding $finding */
        $finding = $collection->first();

        return $finding;
    }
}
