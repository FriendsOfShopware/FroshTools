<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ProductDescriptionTooLongChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    private const MAX_TERM_BYTES = 32766;

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.shopware_version%')]
        private readonly string $shopwareVersion,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        if (version_compare($this->shopwareVersion, '6.6.6.0', '>=')) {
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
            'product-description-too-long',
            \sprintf('Product descriptions longer than %d bytes', self::MAX_TERM_BYTES),
            $count,
        ));
    }
}
