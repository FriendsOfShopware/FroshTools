<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class SalesChannelDomainCurrencyChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'sales-channel-domain-currency-unassigned';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT domain.sales_channel_id AS id
        FROM sales_channel_domain domain
        LEFT JOIN sales_channel_currency assigned
            ON assigned.sales_channel_id = domain.sales_channel_id
            AND assigned.currency_id = domain.currency_id
        WHERE assigned.sales_channel_id IS NULL
        SQL;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM sales_channel_domain domain
                LEFT JOIN sales_channel_currency assigned
                    ON assigned.sales_channel_id = domain.sales_channel_id
                    AND assigned.currency_id = domain.currency_id
                WHERE assigned.sales_channel_id IS NULL
                SQL,
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Domains with a currency that is not assigned to their sales channel',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery($this->connection, 'sales_channel', self::AFFECTED_QUERY, [], [], $limit, $offset);
    }
}
