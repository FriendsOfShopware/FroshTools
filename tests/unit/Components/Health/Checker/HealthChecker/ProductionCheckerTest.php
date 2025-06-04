<?php

namespace Frosh\Tools\Tests\unit\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\HealthChecker\ProductionChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Test\SettingsResultAssertionHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductionChecker::class)]
class ProductionCheckerTest extends TestCase
{
    public static function appEnvDataProvider(): \Generator
    {
        yield 'APP_ENV=prod' => ['prod', SettingsResult::GREEN];
        yield 'APP_ENV=dev' => ['dev', SettingsResult::ERROR];
        yield 'APP_ENV=test' => ['test', SettingsResult::ERROR];
    }

    #[DataProvider('appEnvDataProvider')]
    public function testCheckerResultIsAddedAccordingToAppEnv(string $appEnv, string $expectedCheckerState): void
    {
        $healthCollection = new HealthCollection();

        $productionChecker = new ProductionChecker($appEnv);
        $productionChecker->collect($healthCollection);

        SettingsResultAssertionHelper::assertSingleSettingsResult(
            $healthCollection,
            'app.env',
            $expectedCheckerState,
            $appEnv,
            'prod'
        );
    }

}
