<?php

namespace Frosh\Tools\Components\Elasticsearch;

use Elasticsearch\Client;

class ElasticsearchManager
{
    private Client $client;
    private bool $enabled;

    public function __construct(Client $client, bool $enabled)
    {
        $this->client = $client;
        $this->enabled = $enabled;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function info(): array
    {
        return [
            'info' => $this->client->info(),
            'health' => $this->client->cluster()->health()
        ];
    }

    public function indices(): array
    {
        $indices = $this->client->indices()->get(['index' => '*']);
        $stats = $this->client->indices()->stats(['index' => '*']);

        $list = [];

        foreach ($indices as $indexName => $config) {
            $statCfg = $stats['indices'][$indexName];

            $list[] = [
                'name' => $indexName,
                'aliases' => array_keys($config['aliases']),
                'indexSize' => $statCfg['total']['store']['size_in_bytes'],
                'docs' => $statCfg['primaries']['docs']['count'],
            ];
        }

        return $list;
    }

    public function deleteIndex(string $name): array
    {
        return $this->client->indices()->delete(['index' => $name]);
    }
}
