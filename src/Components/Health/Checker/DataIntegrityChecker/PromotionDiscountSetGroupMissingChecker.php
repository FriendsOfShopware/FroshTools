<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;

class PromotionDiscountSetGroupMissingChecker implements DataIntegrityCheckerInterface, CheckerInterface
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
                FROM promotion_discount pd
                INNER JOIN promotion p ON p.id = pd.promotion_id
                WHERE p.active = 1
                  AND LEFT(CAST(pd.scope AS BINARY), 9) = 'setgroup-'
                  AND (SUBSTRING(pd.scope, 10) NOT REGEXP '^[1-9][0-9]*$'
                      OR CAST(SUBSTRING(pd.scope, 10) AS UNSIGNED) > (SELECT COUNT(*)
                                                                      FROM promotion_setgroup ps
                                                                      WHERE ps.promotion_id = p.id))
                SQL,
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'promotion-discount-setgroup-missing',
            'Promotion discounts referencing a missing set group',
            $count,
        ));
    }
}
