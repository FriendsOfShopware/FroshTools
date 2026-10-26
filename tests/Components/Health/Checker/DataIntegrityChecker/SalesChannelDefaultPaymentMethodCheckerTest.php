<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\SalesChannelDefaultPaymentMethodChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(SalesChannelDefaultPaymentMethodChecker::class)]
class SalesChannelDefaultPaymentMethodCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'sales-channel-default-payment-unassigned';

    private SalesChannelDefaultPaymentMethodChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(SalesChannelDefaultPaymentMethodChecker::class);
        $this->connection->executeStatement(
            'INSERT IGNORE INTO sales_channel_payment_method (sales_channel_id, payment_method_id) SELECT id, payment_method_id FROM sales_channel'
        );
    }

    public function testReportsOkWhenTheDefaultMethodIsAssigned(): void
    {
        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
        static::assertAffectedEntities($this->checker, 'sales_channel', []);
    }

    public function testCountsSalesChannelsWhoseDefaultMethodIsNotAssigned(): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                DELETE assigned
                FROM sales_channel_payment_method assigned
                INNER JOIN sales_channel sc ON sc.id = assigned.sales_channel_id AND sc.payment_method_id = assigned.payment_method_id
                WHERE sc.id = (SELECT id FROM (SELECT id FROM sales_channel ORDER BY id LIMIT 1) first_sales_channel)
                SQL
        );

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 1);
        static::assertAffectedEntities($this->checker, 'sales_channel', [$this->fetchFirstSalesChannelId()]);
    }

    public function testIgnoresProductComparisonSalesChannels(): void
    {
        $this->createProductComparisonSalesChannel();

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
        static::assertAffectedEntities($this->checker, 'sales_channel', []);
    }

    private function createProductComparisonSalesChannel(): void
    {
        /** @var EntityRepository $repository */
        $repository = static::getContainer()->get('sales_channel.repository');
        $repository->create([[
            'id' => Uuid::randomHex(),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON,
            'name' => 'Frosh data integrity check product comparison',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'currencies' => [['id' => Defaults::CURRENCY]],
            'customerGroupId' => $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM customer_group LIMIT 1'),
            'paymentMethodId' => $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM payment_method LIMIT 1'),
            'shippingMethodId' => $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM shipping_method LIMIT 1'),
            'countryId' => $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM country LIMIT 1'),
            'navigationCategoryId' => $this->connection->fetchOne('SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel LIMIT 1'),
        ]], Context::createDefaultContext());
    }

    private function fetchFirstSalesChannelId(): string
    {
        return (string) $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM sales_channel ORDER BY id LIMIT 1');
    }
}
