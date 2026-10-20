<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Health\Checker\DataIntegrityChecker;

use Frosh\Tools\Components\Health\Checker\DataIntegrityChecker\PaymentMethodHandlerMissingChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PrePayment;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(PaymentMethodHandlerMissingChecker::class)]
class PaymentMethodHandlerMissingCheckerTest extends DataIntegrityCheckerTestCase
{
    private const ID = 'payment-method-handler-missing';

    private PaymentMethodHandlerMissingChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->checker = static::getContainer()->get(PaymentMethodHandlerMissingChecker::class);
        $this->connection->executeStatement(
            'UPDATE payment_method SET active = 0 WHERE handler_identifier NOT LIKE :corePrefix OR id IN (SELECT payment_method_id FROM app_payment_method)',
            ['corePrefix' => 'Shopware\\\\Core\\\\%'],
        );
    }

    public function testReportsOkForResolvableHandlersAndUnusedBrokenMethods(): void
    {
        $this->createPaymentMethod(PrePayment::class, true, true);
        $this->createPaymentMethod('Frosh\\Tools\\Tests\\RemovedPaymentHandler', false, true);
        $this->createPaymentMethod('Frosh\\Tools\\Tests\\RemovedPaymentHandler', true, false);
        $this->createAppPaymentMethod($this->createApp(true));
        $this->createAppPaymentMethod($this->createApp(false));

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 0);
    }

    public function testCountsActiveAssignedMethodsWithoutHandlerOrApp(): void
    {
        $this->createPaymentMethod('Frosh\\Tools\\Tests\\RemovedPaymentHandler', true, true);

        $this->createAppPaymentMethod(null);

        static::assertAffectedCount($this->collectResult($this->checker, self::ID), 2);
    }

    private function createAppPaymentMethod(?string $appId): void
    {
        $this->connection->insert('app_payment_method', [
            'id' => Uuid::randomBytes(),
            'app_id' => $appId,
            'payment_method_id' => $this->createPaymentMethod('app\\FroshApp_payment', true, true),
            'app_name' => 'FroshApp',
            'identifier' => 'payment',
            'pay_url' => 'https://example.com/pay',
            'created_at' => self::now(),
        ]);
    }

    private function createPaymentMethod(string $handlerIdentifier, bool $active, bool $assigned): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('payment_method', [
            'id' => $id,
            'handler_identifier' => $handlerIdentifier,
            'technical_name' => 'frosh_data_integrity_check_' . Uuid::randomHex(),
            'active' => (int) $active,
            'created_at' => self::now(),
        ]);

        if ($assigned) {
            $this->connection->insert('sales_channel_payment_method', [
                'sales_channel_id' => $this->connection->fetchOne('SELECT id FROM sales_channel LIMIT 1'),
                'payment_method_id' => $id,
            ]);
        }

        return $id;
    }
}
