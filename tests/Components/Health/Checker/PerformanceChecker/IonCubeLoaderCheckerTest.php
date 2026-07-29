<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\PerformanceChecker\IonCubeLoaderChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IonCubeLoaderChecker::class)]
class IonCubeLoaderCheckerTest extends TestCase
{
    public function testDoesNothingWhenIonCubeLoaderIsNotInstalled(): void
    {
        $collection = new HealthCollection();
        (new IonCubeLoaderChecker(static fn (): bool => false))->collect($collection);

        static::assertCount(0, $collection);
    }

    public function testWarnsWhenIonCubeLoaderIsInstalled(): void
    {
        $collection = new HealthCollection();
        (new IonCubeLoaderChecker(static fn (string $extension): bool => $extension === 'ionCube Loader'))->collect($collection);

        static::assertCount(1, $collection);
        /** @var SettingsResult $result */
        $result = $collection->first();
        static::assertSame(SettingsResult::WARNING, $result->state);
        static::assertSame('ioncube-loader', $result->id);
        static::assertSame('ionCube Loader can drastically decrease performance', $result->getVars()['snippet']);
        static::assertSame('enabled', $result->current);
        static::assertSame('disabled', $result->recommended);
    }
}
