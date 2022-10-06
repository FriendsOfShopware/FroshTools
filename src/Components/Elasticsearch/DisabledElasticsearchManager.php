<?php

namespace Frosh\Tools\Components\Elasticsearch;

class DisabledElasticsearchManager extends ElasticsearchManager
{
    public function isEnabled(): bool
    {
        return false;
    }
}
