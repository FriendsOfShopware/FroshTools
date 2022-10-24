<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Elasticsearch\ElasticsearchManager;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class EsChecker implements CheckerInterface
{
    public const ES_CHECKER_NAME = "Elasticsearcn";
    protected bool $esEnabled = false;

    public function __construct(ElasticsearchManager $elasticsearchManager)
    {
        $this->esEnabled = $elasticsearchManager->isEnabled();
    }

    public function collect(HealthCollection $collection): void
    {
        if (!$this->esEnabled) {
            $collection->add(
                SettingsResult::info('elasticsearch', self::ES_CHECKER_NAME, 'Elasticsearch is disabled',
                    'disabled',
                    'enabled',
                    'https://developer.shopware.com/docs/guides/hosting/infrastructure/infrastructure/elasticsearch-setup'
                )
            );

            return;
        }

        $collection->add(
            SettingsResult::ok('elasticsearch', self::ES_CHECKER_NAME, 'Elasticsearch is enabled',
                'enabled',
                'enabled'
            )
        );
    }
}
