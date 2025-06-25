<?php

namespace Frosh\Tools\Tests\unit\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\HealthChecker\DebugChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Test\SettingsResultAssertionHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;

class DebugCheckerTest extends TestCase
{

    public static function webProfilerCheckDataProvider(): \Generator
    {
        yield 'WebProfilerBundle active' => [
            'bundles' => [WebProfilerBundle::class],
            'expectedState' => SettingsResult::ERROR
        ];

        yield 'WebProfilerBundle inactive' => [
            'bundles' => [],
            'expectedState' => SettingsResult::GREEN
        ];
    }

    #[DataProvider('webProfilerCheckDataProvider')]
    public function testWebProfilerCheck(array $bundles, string $expectedState): void
    {
        $healthCollection = new HealthCollection();
        $debugChecker = new DebugChecker(
            $bundles,
            false
        );

        $debugChecker->checkWebProfiler($healthCollection);

        SettingsResultAssertionHelper::assertSingleSettingsResult(
            $healthCollection,
            'webprofiler',
            $expectedState
        );
    }

    public function testKernelDebugModeCheck(): void
    {
        // Test with debug mode active
        $healthCollection = new HealthCollection();
        $debugChecker = new DebugChecker([], true);

        $debugChecker->checkKernelDebug($healthCollection);

        SettingsResultAssertionHelper::assertSingleSettingsResult(
            $healthCollection,
            'kerneldebug',
            SettingsResult::ERROR,
            'active',
            'not active'
        );

        // Test with debug mode disabled
        $healthCollection = new HealthCollection();
        $debugChecker = new DebugChecker([], false);

        $debugChecker->checkKernelDebug($healthCollection);

        SettingsResultAssertionHelper::assertSingleSettingsResult(
            $healthCollection,
            'kerneldebug',
            SettingsResult::GREEN,
            'not active',
            'not active'
        );
    }

}
