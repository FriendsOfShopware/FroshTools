<?php

namespace Frosh\Tools\Tests\unit\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Elasticsearch\ElasticsearchManager;
use Frosh\Tools\Components\Health\Checker\PerformanceChecker\EsChecker;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use Frosh\Tools\Test\SettingsResultAssertionHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EsChecker::class)]
class EsCheckerTest extends TestCase
{
    public function testCollectAddsInfoWhenElasticsearchIsDisabled(): void
    {
        // Create a mock for ElasticsearchManager
        $elasticsearchManager = $this->createMock(ElasticsearchManager::class);
        
        // Configure the mock to return false for isEnabled()
        $elasticsearchManager->method('isEnabled')
            ->willReturn(false);
        
        // Create the checker with our mock
        $checker = new EsChecker($elasticsearchManager);
        
        // Create a health collection
        $collection = new HealthCollection();
        
        // Call the collect method
        $checker->collect($collection);
        
        // Assert that an info message was added to the collection
        SettingsResultAssertionHelper::assertSingleSettingsResult(
            $collection,
            'elasticsearch',
            SettingsResult::INFO,
            'disabled',
            'enabled',
            'https://developer.shopware.com/docs/guides/hosting/infrastructure/elasticsearch/elasticsearch-setup'
        );
    }
    
    public function testCollectDoesNotAddInfoWhenElasticsearchIsEnabled(): void
    {
        // Create a mock for ElasticsearchManager
        $elasticsearchManager = $this->createMock(ElasticsearchManager::class);
        
        // Configure the mock to return true for isEnabled()
        $elasticsearchManager->method('isEnabled')
            ->willReturn(true);
        
        // Create the checker with our mock
        $checker = new EsChecker($elasticsearchManager);
        
        // Create a health collection
        $collection = new HealthCollection();
        
        // Call the collect method
        $checker->collect($collection);
        
        // Assert that no info message was added to the collection
        SettingsResultAssertionHelper::assertSettingsResultCount($collection, 0);
    }
}
