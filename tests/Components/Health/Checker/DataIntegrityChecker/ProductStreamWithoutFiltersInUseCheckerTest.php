<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\ProductStreamWithoutFiltersInUseChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(ProductStreamWithoutFiltersInUseChecker::class)]
class ProductStreamWithoutFiltersInUseCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'product-stream-without-filters-in-use';

    private const FILTER = '[{"type": "equals", "field": "active", "value": "1"}]';

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection->executeStatement(
            'UPDATE product_stream SET api_filter = :filter, invalid = 0 WHERE api_filter IS NULL OR JSON_LENGTH(api_filter) = 0',
            ['filter' => self::FILTER],
        );
    }

    public function testReportsOkForStreamsWithFiltersAndUnusedStreamsWithoutFilters(): void
    {
        $this->useInCategory($this->createStream(self::FILTER, false));
        $this->useInCrossSelling($this->createStream(self::FILTER, false), true);
        $this->useInProductExport($this->createStream(self::FILTER, false));
        $this->useInCmsSlot($this->createStream(self::FILTER, false));
        $this->createStream(null, true);
        $this->useInCrossSelling($this->createStream(null, true), false);
        $this->useInProductExport($this->createStream('[]', false));
        $this->useInCategory($this->createStream(null, true), false);
        $this->useInCategory($this->createStream(null, true), true, 'folder');

        static::assertAffectedCount($this->collectResult($this->createChecker('6.7.14.0'), self::ID), 0);
    }

    public function testCountsStreamsWithoutFiltersThatAreStillInUse(): void
    {
        $this->useInCategory($this->createStream(null, true));
        $this->useInCrossSelling($this->createStream('[]', false), true);
        $this->useInCmsSlot($this->createStream(null, true));
        $this->useInProductExport($this->createStream(null, true));
        $this->useInProductExport($this->createStream('[]', false));

        $usedTwice = $this->createStream('[]', true);
        $this->useInCategory($usedTwice);
        $this->useInProductExport($usedTwice);

        $this->useInCategorySlotConfig($this->createStream(null, true));
        $this->useInProductSlotConfig($this->createStream(null, true));
        $this->useInLandingPageSlotConfig($this->createStream(null, true));
        $this->useInHomeSlotConfig($this->createStream(null, true));

        static::assertAffectedCount($this->collectResult($this->createChecker('6.7.14.0'), self::ID), 9);
    }

    public function testCountsEmptyStreamsOfProductExportsBefore6_7_14(): void
    {
        $this->useInProductExport($this->createStream('[]', false));

        static::assertAffectedCount($this->collectResult($this->createChecker('6.7.13.0'), self::ID), 1);
    }

    private function createChecker(string $version): ProductStreamWithoutFiltersInUseChecker
    {
        return new ProductStreamWithoutFiltersInUseChecker($this->connection, $version);
    }

    private function createStream(?string $apiFilter, bool $invalid): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('product_stream', [
            'id' => $id,
            'api_filter' => $apiFilter,
            'invalid' => $invalid ? 1 : 0,
            'created_at' => self::now(),
        ]);

        return $id;
    }

    private function useInCategory(string $streamId, bool $active = true, string $type = 'page'): void
    {
        $this->connection->insert('category', [
            'id' => Uuid::randomBytes(),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'active' => $active ? 1 : 0,
            'type' => $type,
            'product_assignment_type' => 'product_stream',
            'product_stream_id' => $streamId,
            'created_at' => self::now(),
        ]);
    }

    private function useInCrossSelling(string $streamId, bool $active): void
    {
        $this->connection->insert('product_cross_selling', [
            'id' => Uuid::randomBytes(),
            'type' => 'productStream',
            'position' => 1,
            'sort_by' => 'name',
            'sort_direction' => 'ASC',
            'active' => $active ? 1 : 0,
            'product_id' => Uuid::fromHexToBytes($this->createProduct()),
            'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'product_stream_id' => $streamId,
            'created_at' => self::now(),
        ]);
    }

    private function useInProductExport(string $streamId): void
    {
        $salesChannelId = $this->connection->fetchOne('SELECT id FROM sales_channel LIMIT 1');

        $this->connection->insert('product_export', [
            'id' => Uuid::randomBytes(),
            'product_stream_id' => $streamId,
            'storefront_sales_channel_id' => $salesChannelId,
            'sales_channel_id' => $salesChannelId,
            'file_name' => Uuid::randomHex() . '.csv',
            'access_key' => Uuid::randomHex(),
            'encoding' => 'UTF-8',
            'file_format' => 'csv',
            '`interval`' => 0,
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'created_at' => self::now(),
        ]);
    }

    private function useInCmsSlot(string $streamId): void
    {
        /** @var EntityRepository $repository */
        $repository = static::getContainer()->get('cms_page.repository');
        $repository->create([[
            'type' => 'landingpage',
            'sections' => [[
                'type' => 'default',
                'position' => 0,
                'blocks' => [[
                    'type' => 'product-slider',
                    'position' => 0,
                    'slots' => [[
                        'type' => 'product-slider',
                        'slot' => 'productSlider',
                        'config' => [
                            'products' => ['source' => 'product_stream', 'value' => Uuid::fromBytesToHex($streamId)],
                        ],
                    ]],
                ]],
            ]],
        ]], Context::createDefaultContext());
    }

    private function useInCategorySlotConfig(string $streamId): void
    {
        $categoryId = Uuid::randomBytes();
        $this->connection->insert('category', [
            'id' => $categoryId,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'type' => 'page',
            'created_at' => self::now(),
        ]);
        $this->connection->insert('category_translation', [
            'category_id' => $categoryId,
            'category_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'slot_config' => $this->slotConfig($streamId),
            'created_at' => self::now(),
        ]);
    }

    private function useInProductSlotConfig(string $streamId): void
    {
        $this->connection->executeStatement(
            'UPDATE product_translation SET slot_config = :slotConfig WHERE product_id = :productId',
            ['slotConfig' => $this->slotConfig($streamId), 'productId' => Uuid::fromHexToBytes($this->createProduct())],
        );
    }

    private function useInLandingPageSlotConfig(string $streamId): void
    {
        $landingPageId = Uuid::randomBytes();
        $this->connection->insert('landing_page', [
            'id' => $landingPageId,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'created_at' => self::now(),
        ]);
        $this->connection->insert('landing_page_translation', [
            'landing_page_id' => $landingPageId,
            'landing_page_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'slot_config' => $this->slotConfig($streamId),
            'created_at' => self::now(),
        ]);
    }

    private function useInHomeSlotConfig(string $streamId): void
    {
        $this->connection->executeStatement(
            'UPDATE sales_channel_translation SET home_slot_config = :slotConfig LIMIT 1',
            ['slotConfig' => $this->slotConfig($streamId)],
        );
    }

    private function slotConfig(string $streamId): string
    {
        return json_encode([
            Uuid::randomHex() => ['products' => ['source' => 'product_stream', 'value' => Uuid::fromBytesToHex($streamId)]],
        ], \JSON_THROW_ON_ERROR);
    }
}
