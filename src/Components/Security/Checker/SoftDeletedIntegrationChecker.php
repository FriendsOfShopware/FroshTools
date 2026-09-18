<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Security\Checker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\SecurityCollection;
use Frosh\Tools\Components\Security\SecurityFinding;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;

/**
 * Deleting an app only soft-deletes its integrations (and ACL roles). The
 * "app_delete" scheduled task (DeleteCascadeAppsHandler) removes them for good
 * once their deletedAt is older than one day. Rows older than that mean the
 * scheduled task is not running and leftover credentials stay in the database.
 */
class SoftDeletedIntegrationChecker implements SecurityCheckerInterface
{
    public const ID = 'soft-deleted-integrations';

    private const HARD_DELETE_AFTER_DAYS = 1;

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function collect(SecurityCollection $collection): void
    {
        $threshold = $this->clock->now()
            ->modify(\sprintf('-%d day', self::HARD_DELETE_AFTER_DAYS))
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        try {
            $count = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM `integration` WHERE `deleted_at` IS NOT NULL AND `deleted_at` <= :threshold',
                ['threshold' => $threshold],
            );
        } catch (\Throwable $e) {
            $collection->add(SecurityFinding::unknown(
                self::ID,
                SecurityFinding::CATEGORY_RUNTIME,
                'Soft-deleted integrations',
                'unknown',
                'Could not check for stale soft-deleted integrations: ' . $e->getMessage(),
            ));

            return;
        }

        if ($count > 0) {
            $collection->add(SecurityFinding::medium(
                self::ID,
                SecurityFinding::CATEGORY_RUNTIME,
                'Soft-deleted integrations',
                \sprintf('%d integration(s) soft-deleted for more than %d day(s)', $count, self::HARD_DELETE_AFTER_DAYS),
                'The scheduled task "app_delete" hard-deletes them daily. Make sure scheduled tasks are executed (bin/console scheduled-task:run) and run it manually to clean up',
            ));

            return;
        }

        $collection->add(SecurityFinding::ok(
            self::ID,
            SecurityFinding::CATEGORY_RUNTIME,
            'Soft-deleted integrations',
            'none pending hard delete',
        ));
    }
}
