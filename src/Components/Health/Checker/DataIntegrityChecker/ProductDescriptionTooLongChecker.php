<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ProductDescriptionTooLongChecker implements DataIntegrityCheckerInterface, CheckerInterface, AffectedEntitiesProviderInterface
{
    private const ID = 'product-description-too-long';

    private const MAX_TERM_BYTES = 32766;

    private const AFFECTED_QUERY = <<<'SQL'
        SELECT product_id AS id
        FROM product_translation
        WHERE product_version_id = :liveVersionId
          AND LENGTH(description) > :maxTermBytes
        SQL;

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.shopware_version%')]
        private readonly string $shopwareVersion,
    ) {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function collect(HealthCollection $collection): void
    {
        if ($this->isFixedInShopware()) {
            return;
        }

        $count = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM product_translation
                WHERE product_version_id = :liveVersionId
                  AND LENGTH(description) > :maxTermBytes
                SQL,
            [
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'maxTermBytes' => self::MAX_TERM_BYTES,
            ],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            self::ID,
            \sprintf('Product descriptions longer than %d bytes', self::MAX_TERM_BYTES),
            $count,
        ));
    }

    public function getAffectedEntities(int $limit, int $offset): AffectedEntities
    {
        if ($this->isFixedInShopware()) {
            return new AffectedEntities('product', 0, []);
        }

        return AffectedEntities::fromQuery(
            $this->connection,
            'product',
            self::AFFECTED_QUERY,
            [
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'maxTermBytes' => self::MAX_TERM_BYTES,
            ],
            [],
            $limit,
            $offset,
        );
    }

    private function isFixedInShopware(): bool
    {
        return version_compare($this->shopwareVersion, '6.6.6.0', '>=');
    }
}
