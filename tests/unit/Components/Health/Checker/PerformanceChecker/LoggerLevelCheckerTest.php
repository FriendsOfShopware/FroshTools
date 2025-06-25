<?php

namespace Frosh\Tools\Tests\unit\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Health\Checker\PerformanceChecker\LoggerLevelChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Test\SettingsResultAssertionHelper;
use Monolog\Handler\AbstractHandler;
use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoggerLevelChecker::class)]
class LoggerLevelCheckerTest extends TestCase
{
    public static function logLevelProvider(): \Generator
    {
        // Levels lower than WARNING should trigger a warning
        yield 'DEBUG level' => [
            'level' => Level::Debug,
            'expectWarning' => true,
            'levelName' => 'DEBUG',
        ];
        
        yield 'INFO level' => [
            'level' => Level::Info,
            'expectWarning' => true,
            'levelName' => 'INFO',
        ];
        
        yield 'NOTICE level' => [
            'level' => Level::Notice,
            'expectWarning' => true,
            'levelName' => 'NOTICE',
        ];
        
        // Levels equal to or higher than WARNING should not trigger a warning
        yield 'WARNING level' => [
            'level' => Level::Warning,
            'expectWarning' => false,
            'levelName' => 'WARNING',
        ];
        
        yield 'ERROR level' => [
            'level' => Level::Error,
            'expectWarning' => false,
            'levelName' => 'ERROR',
        ];
        
        yield 'CRITICAL level' => [
            'level' => Level::Critical,
            'expectWarning' => false,
            'levelName' => 'CRITICAL',
        ];
        
        yield 'ALERT level' => [
            'level' => Level::Alert,
            'expectWarning' => false,
            'levelName' => 'ALERT',
        ];
        
        yield 'EMERGENCY level' => [
            'level' => Level::Emergency,
            'expectWarning' => false,
            'levelName' => 'EMERGENCY',
        ];
    }
    
    #[DataProvider('logLevelProvider')]
    public function testCollect(Level $level, bool $expectWarning, string $levelName): void
    {
        // Create a mock for AbstractHandler
        $handler = $this->createMock(AbstractHandler::class);
        
        // Configure the mock to return the specified level
        $handler->method('getLevel')
            ->willReturn($level);
        
        // Create the checker with our mock
        $checker = new LoggerLevelChecker($handler);
        
        // Create a health collection
        $collection = new HealthCollection();
        
        // Call the collect method
        $checker->collect($collection);
        
        if ($expectWarning) {
            // Assert that a warning was added to the collection
            SettingsResultAssertionHelper::assertSingleSettingsResult(
                $collection,
                'business_logger',
                SettingsResult::WARNING,
                $levelName,
                'min WARNING',
                'https://developer.shopware.com/docs/guides/hosting/performance/performance-tweaks#logging'
            );
        } else {
            // Assert that no warning was added to the collection
            SettingsResultAssertionHelper::assertSettingsResultCount($collection, 0);
        }
    }
}
