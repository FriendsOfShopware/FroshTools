<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\unit\Components\Health;

use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\FrameworkException;

class HealthCollectionTest extends TestCase
{

    public function testOnlySettingsResultsCanBeAdded(): void
    {
        $collection = new HealthCollection();
        $collection->add(SettingsResult::ok('ok-1', 'ok-1'));

        self::assertSame(1, $collection->count());

        $this->expectException(FrameworkException::class);
        $collection->add(new \stdClass());
    }

    public function testSortByState(): void
    {
        $collection = new HealthCollection();

        // Add items in a mixed order
        $okResult1 = SettingsResult::ok('ok-1', 'C-ok');
        $okResult2 = SettingsResult::ok('ok-2', 'A-ok');
        $okResult3 = SettingsResult::ok('ok-3', 'B-ok');
        
        $errorResult1 = SettingsResult::error('error-1', 'Z-error');
        $errorResult2 = SettingsResult::error('error-2', 'Y-error');
        
        $warningResult = SettingsResult::warning('warning-1', 'warning');
        $infoResult = SettingsResult::info('info-1', 'info');

        // Add in random order to test sorting
        $collection->add($okResult1);
        $collection->add($errorResult1);
        $collection->add($infoResult);
        $collection->add($warningResult);
        $collection->add($okResult2);
        $collection->add($errorResult2);
        $collection->add($okResult3);

        // Sort by state
        $collection->sortByState();

        // Get the sorted items
        $items = $collection->getElements();
        $sortedItems = array_values($items);

        // Verify the order: ERROR (alphabetically), WARNING, INFO, GREEN (alphabetically)
        
        // First ERROR items sorted alphabetically by snippet
        self::assertSame($errorResult2, $sortedItems[0], 'Y-error should come before Z-error');
        self::assertSame($errorResult1, $sortedItems[1], 'Z-error should come after Y-error');
        
        // Then WARNING
        self::assertSame($warningResult, $sortedItems[2]);
        
        // Then INFO
        self::assertSame($infoResult, $sortedItems[3]);
        
        // Then OK/GREEN items sorted alphabetically by snippet
        self::assertSame($okResult2, $sortedItems[4], 'A-ok should be first among OK items');
        self::assertSame($okResult3, $sortedItems[5], 'B-ok should be second among OK items');
        self::assertSame($okResult1, $sortedItems[6], 'C-ok should be last among OK items');
    }
}
