<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\SalesChannelDefaultPaymentMethodChecker;
use PHPUnit\Framework\Attributes\CoversClass;

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
    }
}
