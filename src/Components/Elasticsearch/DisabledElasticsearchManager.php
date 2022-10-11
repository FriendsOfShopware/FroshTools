<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Elasticsearch;

class DisabledElasticsearchManager extends ElasticsearchManager
{
    public function __construct()
    {
    }

    public function isEnabled(): bool
    {
        return false;
    }
}
