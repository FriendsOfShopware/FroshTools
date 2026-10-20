<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\DataIntegrityChecker;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class SystemConfigCmsPageMissingChecker implements DataIntegrityCheckerInterface, CheckerInterface
{
    private const CONFIG_KEYS = [
        'core.basicInformation.tosPage',
        'core.basicInformation.revocationPage',
        'core.basicInformation.shippingPaymentInfoPage',
        'core.basicInformation.privacyPage',
        'core.basicInformation.imprintPage',
        'core.basicInformation.http404Page',
        'core.basicInformation.maintenancePage',
        'core.basicInformation.contactPage',
        'core.basicInformation.revocationRequestPage',
        'core.basicInformation.newsletterPage',
        'core.cms.default_category_cms_page',
        'core.cms.default_product_cms_page',
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function collect(HealthCollection $collection): void
    {
        $count = (int) $this->connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM system_config
                WHERE configuration_key IN (:configKeys)
                  AND JSON_TYPE(JSON_EXTRACT(configuration_value, '$._value')) = 'STRING'
                  AND JSON_UNQUOTE(JSON_EXTRACT(configuration_value, '$._value')) != ''
                  AND NOT EXISTS (
                      SELECT 1
                      FROM cms_page
                      WHERE cms_page.id = UNHEX(JSON_UNQUOTE(JSON_EXTRACT(system_config.configuration_value, '$._value')))
                        AND cms_page.version_id = :liveVersionId
                  )
                SQL,
            [
                'configKeys' => self::CONFIG_KEYS,
                'liveVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            ['configKeys' => ArrayParameterType::STRING],
        );

        $collection->add(DataIntegrityCheckResult::fromCount(
            'system-config-cms-page-missing',
            'Shop pages and default layouts pointing to deleted layouts',
            $count,
        ));
    }
}
