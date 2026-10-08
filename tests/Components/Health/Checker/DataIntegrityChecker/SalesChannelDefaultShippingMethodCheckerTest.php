<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\SalesChannelDefaultShippingMethodChecker;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SalesChannelDefaultShippingMethodChecker::class)]
class SalesChannelDefaultShippingMethodCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'sales-channel-default-shipping-unassigned';

    private SalesChannelDefaultShippingMethodChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(SalesChannelDefaultShippingMethodChecker::class);
        $this->connection->executeStatement(
            'INSERT IGNORE INTO sales_channel_shipping_method (sales_channel_id, shipping_method_id) SELECT id, shipping_method_id FROM sales_channel'
        );
    }

    public function testReportsOkWhenTheDefaultMethodIsAssigned(): void
    {
        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsSalesChannelsWhoseDefaultMethodIsNotAssigned(): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                DELETE assigned
                FROM sales_channel_shipping_method assigned
                INNER JOIN sales_channel sc ON sc.id = assigned.sales_channel_id AND sc.shipping_method_id = assigned.shipping_method_id
                WHERE sc.id = (SELECT id FROM (SELECT id FROM sales_channel ORDER BY id LIMIT 1) first_sales_channel)
                SQL
        );

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 1);
    }
}
