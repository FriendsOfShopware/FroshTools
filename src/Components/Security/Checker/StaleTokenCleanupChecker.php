<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Checker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Customer\CleanupCustomerRecoveryTask;
use Shopware\Core\Checkout\Payment\Cleanup\CleanupPaymentTokenTask;
use Shopware\Core\Defaults;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Several scheduled tasks hard-delete expired tokens daily. When they do not
 * run, valid password-recovery tokens, expired payment tokens and old Store-API
 * sessions stay in the database. Each check mirrors the WHERE condition of the
 * corresponding core handler.
 */
class StaleTokenCleanupChecker implements SecurityCheckerInterface
{
    private const RECOVERY_TOKEN_MAX_AGE_HOURS = 48;

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        #[Autowire(param: 'shopware.sales_channel_context.expire_days')]
        private readonly int $contextExpireDays,
    ) {
    }

    public function collect(SecurityCollection $collection): void
    {
        // customer.cleanup_customer_recovery exists since 6.7.9 and
        // payment_token.cleanup since 6.7.5. On older releases there is no
        // scheduled task to point operators to, so a finding would report
        // leftovers without any usable remediation.
        if (class_exists(CleanupCustomerRecoveryTask::class)) {
            $this->checkRecoveryTokens($collection);
        }

        if (class_exists(CleanupPaymentTokenTask::class)) {
            $this->checkPaymentTokens($collection);
        }

        $this->checkSalesChannelContexts($collection);
    }

    private function checkRecoveryTokens(SecurityCollection $collection): void
    {
        $id = 'stale-customer-recovery-tokens';
        $title = 'Password recovery tokens';

        $threshold = $this->now()->modify(\sprintf('-%d hour', self::RECOVERY_TOKEN_MAX_AGE_HOURS));

        $count = $this->count(
            'SELECT COUNT(*) FROM `customer_recovery` WHERE `created_at` <= :threshold',
            ['threshold' => $threshold],
        );

        if ($count === null) {
            $collection->add(SecurityFinding::unknown($id, SecurityFinding::CATEGORY_RUNTIME, $title, 'unknown', 'Could not check the customer_recovery table'));

            return;
        }

        if ($count > 0) {
            $collection->add(SecurityFinding::medium(
                $id,
                SecurityFinding::CATEGORY_RUNTIME,
                $title,
                \sprintf('%d recovery token(s) older than %d hours', $count, self::RECOVERY_TOKEN_MAX_AGE_HOURS),
                'The scheduled task "customer.cleanup_customer_recovery" deletes them daily. Make sure scheduled tasks are executed (bin/console scheduled-task:run) and run it manually to clean up',
            ));

            return;
        }

        $collection->add(SecurityFinding::ok($id, SecurityFinding::CATEGORY_RUNTIME, $title, 'none pending cleanup'));
    }

    private function checkPaymentTokens(SecurityCollection $collection): void
    {
        $id = 'stale-payment-tokens';
        $title = 'Expired payment tokens';

        $count = $this->count(
            'SELECT COUNT(*) FROM `payment_token` WHERE `expires` < :now',
            ['now' => $this->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
        );

        if ($count === null) {
            $collection->add(SecurityFinding::unknown($id, SecurityFinding::CATEGORY_RUNTIME, $title, 'unknown', 'Could not check the payment_token table'));

            return;
        }

        if ($count > 0) {
            $collection->add(SecurityFinding::medium(
                $id,
                SecurityFinding::CATEGORY_RUNTIME,
                $title,
                \sprintf('%d expired payment token(s) still stored', $count),
                'The scheduled task "payment_token.cleanup" deletes them daily. Make sure scheduled tasks are executed (bin/console scheduled-task:run) and run it manually to clean up',
            ));

            return;
        }

        $collection->add(SecurityFinding::ok($id, SecurityFinding::CATEGORY_RUNTIME, $title, 'none pending cleanup'));
    }

    private function checkSalesChannelContexts(SecurityCollection $collection): void
    {
        $id = 'stale-sales-channel-contexts';
        $title = 'Stale sales channel contexts';

        $threshold = $this->now()->modify(\sprintf('-%d day', $this->contextExpireDays));

        $count = $this->count(
            'SELECT COUNT(*) FROM `sales_channel_api_context` WHERE `updated_at` <= :threshold',
            ['threshold' => $threshold],
        );

        if ($count === null) {
            $collection->add(SecurityFinding::unknown($id, SecurityFinding::CATEGORY_RUNTIME, $title, 'unknown', 'Could not check the sales_channel_api_context table'));

            return;
        }

        if ($count > 0) {
            $collection->add(SecurityFinding::medium(
                $id,
                SecurityFinding::CATEGORY_RUNTIME,
                $title,
                \sprintf('%d Store-API session(s) older than %d days', $count, $this->contextExpireDays),
                'The scheduled task "sales_channel_context.cleanup" deletes them daily. Make sure scheduled tasks are executed (bin/console scheduled-task:run) and run it manually to clean up',
            ));

            return;
        }

        $collection->add(SecurityFinding::ok($id, SecurityFinding::CATEGORY_RUNTIME, $title, 'none pending cleanup'));
    }

    /**
     * @param array<string, string|\DateTimeInterface> $params
     */
    private function count(string $sql, array $params): ?int
    {
        $params = array_map(
            static fn (string|\DateTimeInterface $value): string => $value instanceof \DateTimeInterface ? $value->format(Defaults::STORAGE_DATE_TIME_FORMAT) : $value,
            $params,
        );

        try {
            return (int) $this->connection->fetchOne($sql, $params);
        } catch (\Throwable) {
            return null;
        }
    }

    private function now(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone(new \DateTimeZone('UTC'));
    }
}
