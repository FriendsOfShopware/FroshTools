<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class SalesChannelDomainLanguageChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM sales_channel_domain domain
                LEFT JOIN sales_channel_language assigned
                    ON assigned.sales_channel_id = domain.sales_channel_id
                    AND assigned.language_id = domain.language_id
                WHERE assigned.sales_channel_id IS NULL
                SQL,
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'sales-channel-domain-language-unassigned',
            'Domains with a language that is not assigned to their sales channel',
            $count,
        ));
    }
}
