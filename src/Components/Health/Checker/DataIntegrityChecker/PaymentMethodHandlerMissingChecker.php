<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;

class PaymentMethodHandlerMissingChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'payment-method-handler-missing';

    public function __construct(
        private readonly Connection $connection,
        private readonly PaymentHandlerRegistry $paymentHandlerRegistry,
    ) {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function collect(HealthCollection $collection): void
    {
        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Active payment methods without an available payment handler',
            \count($this->findAffectedIds()),
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromIds('payment_method', $this->findAffectedIds(), $limit, $offset);
    }

    /**
     * @return list<string>
     */
    private function findAffectedIds(): array
    {
        /** @var list<array{id: string, app_payment_method_id: string|null, app_id: string|null}> $paymentMethods */
        $paymentMethods = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT LOWER(HEX(pm.id)) AS id,
                       apm.id AS app_payment_method_id,
                       app.id AS app_id
                FROM payment_method pm
                LEFT JOIN app_payment_method apm ON apm.payment_method_id = pm.id
                LEFT JOIN app ON app.id = apm.app_id
                WHERE pm.active = 1
                  AND EXISTS (SELECT 1 FROM sales_channel_payment_method scpm WHERE scpm.payment_method_id = pm.id)
                SQL,
        );

        $ids = [];
        foreach ($paymentMethods as $paymentMethod) {
            if ($paymentMethod['app_payment_method_id'] !== null && $paymentMethod['app_id'] === null) {
                $ids[] = $paymentMethod['id'];

                continue;
            }

            if ($this->paymentHandlerRegistry->getPaymentMethodHandler($paymentMethod['id']) === null) {
                $ids[] = $paymentMethod['id'];
            }
        }

        return $ids;
    }
}
