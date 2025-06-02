<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\HealthChecker;

use Frosh\Tools\Components\Health\Checker\HealthChecker\ProductionChecker;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Tests\Components\Health\Checker\AbstractCheckerTestCase;

class ProductionCheckerTest extends AbstractCheckerTestCase
{
    public function testCollectWithProductionEnvironment(): void
    {
        $checker = new ProductionChecker('prod');
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'app.env', SettingsResult::GREEN);
        
        $result = $this->getResultById($collection, 'app.env');
        $this->assertNotNull($result);
        $this->assertEquals('prod', $result->current);
        $this->assertEquals('prod', $result->recommended);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonProductionEnvironmentProvider')]
    public function testCollectWithNonProductionEnvironment(string $environment): void
    {
        $checker = new ProductionChecker($environment);
        $collection = $this->createHealthCollection();
        
        $checker->collect($collection);
        
        $this->assertHealthCollectionCount($collection, 1);
        $this->assertHealthCollectionContains($collection, 'app.env', SettingsResult::ERROR);
        
        $result = $this->getResultById($collection, 'app.env');
        $this->assertNotNull($result);
        $this->assertEquals($environment, $result->current);
        $this->assertEquals('prod', $result->recommended);
    }

    public static function nonProductionEnvironmentProvider(): array
    {
        return [
            ['dev'],
            ['test'],
            ['staging'],
            ['local'],
            ['development'],
        ];
    }
}