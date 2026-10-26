<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class CategoryLinkTargetInvalidChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'category-link-target-invalid';

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT c.id
        FROM category c
        LEFT JOIN category_translation system_translation
            ON system_translation.category_id = c.id
            AND system_translation.category_version_id = c.version_id
            AND system_translation.language_id = :systemLanguageId
        WHERE c.version_id = :liveVersionId
          AND c.type = 'link'
          AND (
              system_translation.category_id IS NULL
              OR (
                  system_translation.link_type IN ('product', 'category', 'landing_page')
                  AND system_translation.internal_link IS NULL
              )
              OR (
                  COALESCE(system_translation.link_type, 'external') NOT IN ('product', 'category', 'landing_page')
                  AND COALESCE(system_translation.external_link, '') = ''
              )
              OR EXISTS (
                  SELECT 1
                  FROM category_translation ct
                  WHERE ct.category_id = c.id
                    AND ct.category_version_id = c.version_id
                    AND ct.external_link = ''
                    AND COALESCE(ct.link_type, system_translation.link_type, 'external') NOT IN ('product', 'category', 'landing_page')
              )
              OR EXISTS (
                  SELECT 1
                  FROM category_translation ct
                  WHERE ct.category_id = c.id
                    AND ct.category_version_id = c.version_id
                    AND COALESCE(ct.link_type, system_translation.link_type) IN ('product', 'category', 'landing_page')
                    AND COALESCE(ct.internal_link, system_translation.internal_link) IS NULL
              )
              OR EXISTS (
                  SELECT 1
                  FROM category_translation ct
                  WHERE ct.category_id = c.id
                    AND ct.category_version_id = c.version_id
                    AND ct.internal_link IS NOT NULL
                    AND (
                        (ct.link_type = 'product' AND NOT EXISTS (
                            SELECT 1 FROM product WHERE product.id = ct.internal_link AND product.version_id = :liveVersionId
                        ))
                        OR (ct.link_type = 'category' AND NOT EXISTS (
                            SELECT 1 FROM category target WHERE target.id = ct.internal_link AND target.version_id = :liveVersionId
                        ))
                        OR (ct.link_type = 'landing_page' AND NOT EXISTS (
                            SELECT 1 FROM landing_page WHERE landing_page.id = ct.internal_link AND landing_page.version_id = :liveVersionId
                        ))
                    )
              )
          )
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
            \sprintf('SELECT COUNT(*) FROM (%s) affected', self::AFFECTED_QUERY),
            $this->queryParameters(),
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            'Link categories without a valid link target',
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        return AffectedEntities::fromQuery($this->connection, 'category', self::AFFECTED_QUERY, $this->queryParameters(), [], $limit, $offset);
    }

    /**
     * @return array{liveVersionId: string, systemLanguageId: string}
     */
    private function queryParameters(): array
    {
        return [
            'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'systemLanguageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
        ];
    }
}
