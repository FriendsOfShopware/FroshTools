<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health;

use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use PHPUnit\Framework\TestCase;

class HealthCollectionTest extends TestCase
{
    public function testExpectedClass(): void
    {
        $collection = new HealthCollection();
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($collection);
        $method = $reflection->getMethod('getExpectedClass');
        $method->setAccessible(true);
        
        $this->assertEquals(SettingsResult::class, $method->invoke($collection));
    }

    public function testAddSettingsResult(): void
    {
        $collection = new HealthCollection();
        
        $result1 = SettingsResult::ok('test1', 'Test 1');
        $result2 = SettingsResult::warning('test2', 'Test 2');
        
        $collection->add($result1);
        $collection->add($result2);
        
        $this->assertCount(2, $collection);
        
        // The collection might not use the id as the key automatically
        // Let's check what's actually in the collection
        $elements = $collection->getElements();
        $this->assertCount(2, $elements);
        
        // Check that both results are in the collection
        $this->assertContains($result1, $elements);
        $this->assertContains($result2, $elements);
    }

    public function testSortByStateWithAllStates(): void
    {
        $collection = new HealthCollection();
        
        // Add results in random order
        $infoResult = SettingsResult::info('info', 'Info message');
        $errorResult = SettingsResult::error('error', 'Error message');
        $okResult = SettingsResult::ok('ok', 'OK message');
        $warningResult = SettingsResult::warning('warning', 'Warning message');
        
        $collection->add($infoResult);
        $collection->add($errorResult);
        $collection->add($okResult);
        $collection->add($warningResult);
        
        $collection->sortByState();
        
        $elements = array_values($collection->getElements());
        
        // Should be sorted: ERROR, WARNING, INFO, GREEN
        $this->assertEquals(SettingsResult::ERROR, $elements[0]->state);
        $this->assertEquals(SettingsResult::WARNING, $elements[1]->state);
        $this->assertEquals(SettingsResult::INFO, $elements[2]->state);
        $this->assertEquals(SettingsResult::GREEN, $elements[3]->state);
    }

    public function testSortByStateWithDuplicateStates(): void
    {
        $collection = new HealthCollection();
        
        // Add multiple results with same state
        $error1 = SettingsResult::error('error1', 'Error 1');
        $error2 = SettingsResult::error('error2', 'Error 2');
        $warning1 = SettingsResult::warning('warning1', 'Warning 1');
        $warning2 = SettingsResult::warning('warning2', 'Warning 2');
        $ok1 = SettingsResult::ok('ok1', 'OK 1');
        
        $collection->add($ok1);
        $collection->add($error2);
        $collection->add($warning1);
        $collection->add($error1);
        $collection->add($warning2);
        
        $collection->sortByState();
        
        $elements = array_values($collection->getElements());
        
        // Check that all errors come first
        $this->assertEquals(SettingsResult::ERROR, $elements[0]->state);
        $this->assertEquals(SettingsResult::ERROR, $elements[1]->state);
        
        // Then warnings
        $this->assertEquals(SettingsResult::WARNING, $elements[2]->state);
        $this->assertEquals(SettingsResult::WARNING, $elements[3]->state);
        
        // Then OK
        $this->assertEquals(SettingsResult::GREEN, $elements[4]->state);
    }

    public function testSortByStateEmptyCollection(): void
    {
        $collection = new HealthCollection();
        
        // Should not throw exception
        $collection->sortByState();
        
        $this->assertCount(0, $collection);
    }

    public function testSortByStateWithUnknownState(): void
    {
        $collection = new HealthCollection();
        
        // Create a result with an unknown state using reflection
        $result = SettingsResult::ok('test', 'Test');
        $reflection = new \ReflectionClass($result);
        $stateProperty = $reflection->getProperty('state');
        $stateProperty->setAccessible(true);
        $stateProperty->setValue($result, 'UNKNOWN_STATE');
        
        $collection->add($result);
        $collection->add(SettingsResult::ok('ok', 'OK'));
        
        $collection->sortByState();
        
        $elements = array_values($collection->getElements());
        
        // Unknown state should be sorted to the beginning (priority -1)
        $this->assertEquals('UNKNOWN_STATE', $elements[0]->state);
        $this->assertEquals(SettingsResult::GREEN, $elements[1]->state);
    }

    public function testIterator(): void
    {
        $collection = new HealthCollection();
        
        $result1 = SettingsResult::ok('test1', 'Test 1');
        $result2 = SettingsResult::warning('test2', 'Test 2');
        
        $collection->add($result1);
        $collection->add($result2);
        
        $items = [];
        foreach ($collection as $key => $item) {
            $items[$key] = $item;
        }
        
        $this->assertCount(2, $items);
        // The collection may use numeric keys or other keys
        $this->assertContains($result1, $items);
        $this->assertContains($result2, $items);
    }
}