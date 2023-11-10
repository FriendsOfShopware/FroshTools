<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Health\Checker\PerformanceChecker;

use Frosh\Tools\Components\Elasticsearch\ElasticsearchManager;
use Frosh\Tools\Components\Health\Checker\CheckerInterface;
use Frosh\Tools\Components\Health\HealthCollection;
use Frosh\Tools\Components\Health\SettingsResult;

class EsChecker implements PerformanceCheckerInterface, CheckerInterface
{
    protected bool $esEnabled = false;

    public function __construct(ElasticsearchManager $elasticsearchManager)
    {
        $this->esEnabled = $elasticsearchManager->isEnabled();
    }

    public function collect(HealthCollection $collection): void
    {
        if (!$this->esEnabled) {
            $collection->add(
                SettingsResult::info(
                    'elasticsearch',
                    'Elasticsearch',
                    'disabled',
                    'enabled',
                    'https://developer.shopware.com/docs/guides/hosting/infrastructure/elasticsearch/elasticsearch-setup'
                )
            );
        }
    }
}
