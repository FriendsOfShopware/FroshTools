<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\SystemConfigCmsPageMissingChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(SystemConfigCmsPageMissingChecker::class)]
class SystemConfigCmsPageMissingCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'system-config-cms-page-missing';

    private SystemConfigCmsPageMissingChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(SystemConfigCmsPageMissingChecker::class);
        $this->connection->executeStatement(
            'DELETE FROM system_config WHERE configuration_key LIKE :basicInformation OR configuration_key LIKE :cms',
            ['basicInformation' => 'core.basicInformation.%Page', 'cms' => 'core.cms.default_%_cms_page'],
        );
    }

    public function testReportsOkWhenConfiguredPagesExist(): void
    {
        $this->setConfig('core.basicInformation.tosPage', $this->createCmsPage('page'));
        $this->setConfig('core.basicInformation.http404Page', $this->createCmsPage('page'), $this->salesChannelId());
        $this->setConfig('core.basicInformation.imprintPage', '');
        $this->setConfig('core.basicInformation.privacyPage', null);
        $this->setConfig('core.cms.default_category_cms_page', $this->createCmsPage('product_list'));

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsConfiguredPagesThatDoNotExist(): void
    {
        $this->setConfig('core.basicInformation.tosPage', Uuid::randomHex());
        $this->setConfig('core.basicInformation.http404Page', Uuid::randomHex(), $this->salesChannelId());
        $this->setConfig('core.basicInformation.maintenancePage', $this->createCmsPage('page'));
        $this->setConfig('core.cms.default_product_cms_page', Uuid::randomHex());
        $this->setConfig('core.basicInformation.shopName', Uuid::randomHex());

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 3);
    }

    private function setConfig(string $key, ?string $value, ?string $salesChannelId = null): void
    {
        $this->connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => $key,
            'configuration_value' => json_encode(['_value' => $value], \JSON_THROW_ON_ERROR),
            'sales_channel_id' => $salesChannelId,
            'created_at' => self::now(),
        ]);
    }

    private function salesChannelId(): string
    {
        $salesChannelId = $this->connection->fetchOne('SELECT id FROM sales_channel LIMIT 1');
        static::assertIsString($salesChannelId);

        return $salesChannelId;
    }
}
