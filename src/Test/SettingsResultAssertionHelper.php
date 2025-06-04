<?php

declare(strict_types=1);

namespace Frosh\Tools\Test;

use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

/**
 * Helper class for asserting SettingsResult items in HealthCollection
 */
class SettingsResultAssertionHelper
{
    /**
     * Asserts that a SettingsResult with the given ID exists in the collection
     */
    public static function assertSettingsResultExists(HealthCollection $collection, string $id): SettingsResult
    {
        $result = null;
        
        /** @var SettingsResult $item */
        foreach ($collection as $item) {
            if ($item->getId() === $id) {
                $result = $item;
                break;
            }
        }
        
        Assert::assertNotNull($result, sprintf('SettingsResult with ID "%s" not found in collection', $id));
        
        return $result;
    }
    
    /**
     * Asserts that a SettingsResult with the given properties exists in the collection
     */
    public static function assertSettingsResultMatches(
        HealthCollection $collection,
        string $id,
        string $expectedState,
        string $expectedCurrent = '',
        string $expectedRecommended = '',
        ?string $expectedUrl = null,
        ?string $expectedSnippet = null
    ): SettingsResult {
        $result = self::assertSettingsResultExists($collection, $id);
        
        Assert::assertSame(
            $expectedState, 
            $result->state, 
            sprintf('SettingsResult "%s" has incorrect state', $id)
        );
        
        if ($expectedCurrent !== '') {
            Assert::assertSame(
                $expectedCurrent, 
                $result->current, 
                sprintf('SettingsResult "%s" has incorrect current value', $id)
            );
        }
        
        if ($expectedRecommended !== '') {
            Assert::assertSame(
                $expectedRecommended, 
                $result->recommended, 
                sprintf('SettingsResult "%s" has incorrect recommended value', $id)
            );
        }
        
        if ($expectedUrl !== null) {
            Assert::assertSame(
                $expectedUrl, 
                $result->url, 
                sprintf('SettingsResult "%s" has incorrect URL', $id)
            );
        }
        
        if ($expectedSnippet !== null) {
            Assert::assertSame(
                $expectedSnippet, 
                $result->snippet, 
                sprintf('SettingsResult "%s" has incorrect snippet', $id)
            );
        }
        
        return $result;
    }
    
    /**
     * Asserts that a collection contains exactly one SettingsResult with the given ID and properties
     */
    public static function assertSingleSettingsResult(
        HealthCollection $collection,
        string $id,
        string $expectedState,
        string $expectedCurrent = '',
        string $expectedRecommended = '',
        ?string $expectedUrl = null,
        ?string $expectedSnippet = null
    ): void {
        Assert::assertCount(
            1, 
            $collection, 
            'Collection should contain exactly one SettingsResult'
        );
        
        self::assertSettingsResultMatches(
            $collection,
            $id,
            $expectedState,
            $expectedCurrent,
            $expectedRecommended,
            $expectedUrl,
            $expectedSnippet
        );
    }
    
    /**
     * Asserts that a collection contains no SettingsResult with the given ID
     */
    public static function assertSettingsResultNotExists(HealthCollection $collection, string $id): void
    {
        $found = false;
        
        /** @var SettingsResult $item */
        foreach ($collection as $item) {
            if ($item->getId() === $id) {
                $found = true;
                break;
            }
        }
        
        Assert::assertFalse($found, sprintf('SettingsResult with ID "%s" should not exist in collection', $id));
    }
    
    /**
     * Asserts that a collection contains the expected number of SettingsResult items
     */
    public static function assertSettingsResultCount(HealthCollection $collection, int $expectedCount): void
    {
        Assert::assertCount(
            $expectedCount, 
            $collection, 
            sprintf('Collection should contain exactly %d SettingsResult(s)', $expectedCount)
        );
    }
}
