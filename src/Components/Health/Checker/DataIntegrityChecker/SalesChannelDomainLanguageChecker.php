<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class SalesChannelDomainLanguageChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'sales-channel-domain-language-unassigned';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT domain.sales_channel_id AS id
        FROM sales_channel_domain domain
        LEFT JOIN sales_channel_language assigned
            ON assigned.sales_channel_id = domain.sales_channel_id
            AND assigned.language_id = domain.language_id
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
        $count = AffectedEntities::countRows($this->connection, self::AFFECTED_QUERY);

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Domains with a language that is not assigned to their sales channel',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery($this->connection, SalesChannelDefinition::ENTITY_NAME, self::AFFECTED_QUERY, [], [], $limit, $offset);
    }
}
