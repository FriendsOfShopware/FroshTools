<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker;

use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use PHPUnit\Framework\TestCase;

abstract class AbstractCheckerTestCase extends TestCase
{
    protected function createHealthCollection(): HealthCollection
    {
        return new HealthCollection();
    }

    protected function assertHealthCollectionContains(HealthCollection $collection, string $id, string $state): void
    {
        $found = false;
        $actualState = null;
        
        /** @var SettingsResult $result */
        foreach ($collection as $result) {
            $resultId = $this->getProtectedProperty($result, 'id');
            if ($resultId === $id) {
                $found = true;
                $actualState = $result->state;
                break;
            }
        }
        
        $this->assertTrue($found, sprintf('Expected to find result with id "%s" in collection, but it was not found', $id));
        $this->assertEquals($state, $actualState, sprintf('Expected result with id "%s" to have state "%s", but got "%s"', $id, $state, $actualState));
    }

    protected function assertHealthCollectionCount(HealthCollection $collection, int $count): void
    {
        $this->assertCount($count, $collection, sprintf('Expected collection to contain %d items, but found %d', $count, $collection->count()));
    }

    protected function getResultById(HealthCollection $collection, string $id): ?SettingsResult
    {
        /** @var SettingsResult $result */
        foreach ($collection as $result) {
            $resultId = $this->getProtectedProperty($result, 'id');
            if ($resultId === $id) {
                return $result;
            }
        }
        
        return null;
    }
    
    protected function getProtectedProperty($object, string $property)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        
        return $property->getValue($object);
    }
}