<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Elasticsearch;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use Shopware\Core\Framework\Increment\Exception\IncrementGatewayNotFoundException;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

class ElasticsearchManager
{
    public function __construct(
        private readonly Client $client,
        #[Autowire(param: 'frosh_tools.elasticsearch.enabled')]
        private readonly bool $enabled,
        private readonly ElasticsearchIndexer $indexer,
        private readonly MessageBusInterface $messageBus,
        private readonly CreateAliasTaskHandler $createAliasTaskHandler,
        private readonly ElasticsearchOutdatedIndexDetector $outdatedIndexDetector,
        private readonly Connection $connection,
        #[Autowire(service: 'shopware.increment.gateway.registry')]
        private readonly IncrementGatewayRegistry $gatewayRegistry,
        #[Autowire('%elasticsearch.index_prefix%')]
        private readonly string $indexPrefix = 'sw',
        #[Autowire('%elasticsearch.administration.index_prefix%')]
        private readonly string $adminIndexPrefix = 'sw-admin',
        #[Autowire(param: 'frosh_tools.elasticsearch.show_all_indices')]
        private readonly bool $showAllIndices = false,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return array<mixed>
     */
    public function info(): array
    {
        return [
            'info' => $this->client->info(),
            'health' => $this->client->cluster()->health(),
        ];
    }

    /**
     * @return array<mixed>
     */
    public function indices(): array
    {
        $patterns = $this->showAllIndices
            ? ['*']
            : [
                $this->indexPrefix . '_*',
                $this->adminIndexPrefix . '-*',
            ];

        $list = [];

        foreach ($patterns as $pattern) {
            $indices = $this->client->indices()->get(['index' => $pattern]);
            $stats = $this->client->indices()->stats(['index' => $pattern]);

            foreach ($indices as $indexName => $config) {
                $statCfg = $stats['indices'][$indexName];

                $list[] = [
                    'name' => $indexName,
                    'aliases' => array_keys($config['aliases']),
                    'indexSize' => $statCfg['total']['store']['size_in_bytes'],
                    'docs' => $statCfg['primaries']['docs']['count'],
                ];
            }
        }

        return $list;
    }

    /**
     * @return array<mixed>
     */
    public function deleteIndex(string $name): array
    {
        if (!$this->showAllIndices && !$this->matchesPrefix($name)) {
            throw new \InvalidArgumentException(\sprintf('Index "%s" does not match the configured prefix', $name));
        }

        return $this->client->indices()->delete(['index' => $name]);
    }

    /**
     * @param array<mixed> $params
     *
     * @return iterable<mixed>|string|null
     */
    public function proxy(string $method, string $path, array $params, mixed $body): iterable|string|null
    {
        if ($body === []) {
            $body = null;
        }

        $response = $this->client->request($method, $path, [
            'params' => $params,
            'body' => $body,
        ]);

        if (\is_callable($response)) {
            $response = $response();
        }

        if (!\is_iterable($response) && !\is_string($response) && $response !== null) {
            throw new \UnexpectedValueException('Unexpected Elasticsearch proxy response type.');
        }

        return $response;
    }

    public function flushAll(): void
    {
        $this->client->indices()->flush(['index' => '_all']);
    }

    public function reindex(): void
    {
        $offset = null;
        while ($message = $this->indexer->iterate($offset)) {
            $this->messageBus->dispatch($message);
            $offset = $message->getOffset();
        }
    }

    public function switchAlias(): void
    {
        $this->createAliasTaskHandler->run();
    }

    /**
     * @return array{indices: array<string>, error: string|null}
     */
    public function getUnusedIndices(): array
    {
        try {
            $indices = $this->outdatedIndexDetector->get() ?? [];
        } catch (\Throwable $e) {
            return ['indices' => [], 'error' => $e->getMessage()];
        }

        return ['indices' => array_values($indices), 'error' => null];
    }

    /**
     * @return array{deleted: array<string>, errors: array<string, string>}
     */
    public function deleteUnusedIndices(): array
    {
        $result = ['deleted' => [], 'errors' => []];

        try {
            $indices = $this->outdatedIndexDetector->get() ?? [];
        } catch (\Throwable $e) {
            $result['errors']['__detector__'] = $e->getMessage();

            return $result;
        }

        foreach ($indices as $index) {
            try {
                $this->client->indices()->delete(['index' => $index]);
                $result['deleted'][] = $index;
            } catch (\Throwable $e) {
                $result['errors'][$index] = $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Aggressive detection: lists all indices that match the configured prefix
     * but are NOT attached to any alias. This catches orphaned indices from
     * removed entity definitions or older Shopware versions that the strict
     * ElasticsearchOutdatedIndexDetector::get() does not consider "outdated".
     *
     * Safety nets: any index that has at least one alias or is still tracked in
     * Shopware's indexing task tables is excluded.
     *
     * @return array{indices: array<string>, error: string|null}
     */
    public function getOrphanedIndices(): array
    {
        try {
            $indices = $this->client->indices()->get(['index' => '*']);
            $activeIndexTasks = $this->getActiveIndexTaskMap();
        } catch (\Throwable $e) {
            return ['indices' => [], 'error' => $e->getMessage()];
        }

        return ['indices' => $this->filterOrphanedIndices($indices, $activeIndexTasks), 'error' => null];
    }

    /**
     * @param list<string> $indices
     *
     * @return array{deleted: array<string>, errors: array<string, string>}
     */
    public function deleteOrphanedIndices(array $indices): array
    {
        $result = ['deleted' => [], 'errors' => []];
        $indices = array_values(array_unique($indices));

        if ($indices === []) {
            return $result;
        }

        try {
            $currentIndices = $this->client->indices()->get(['index' => '*']);
            $activeIndexTasks = $this->getActiveIndexTaskMap();
        } catch (\Throwable $e) {
            $result['errors']['__detector__'] = $e->getMessage();

            return $result;
        }

        foreach ($indices as $index) {
            $config = $currentIndices[$index] ?? null;
            if (!\is_array($config)) {
                $result['errors'][$index] = 'Index does not exist.';

                continue;
            }

            if (!$this->isOrphanedIndex($index, $config, $activeIndexTasks)) {
                $result['errors'][$index] = 'Index is no longer orphaned.';

                continue;
            }

            try {
                $this->client->indices()->delete(['index' => $index]);
                $result['deleted'][] = $index;
            } catch (\Throwable $e) {
                $result['errors'][$index] = $e->getMessage();
            }
        }

        return $result;
    }

    public function reset(): void
    {
        $indices = $this->outdatedIndexDetector->getAllUsedIndices();

        foreach ($indices as $index) {
            $this->client->indices()->delete(['index' => $index]);
        }

        $this->connection->executeStatement('TRUNCATE elasticsearch_index_task');

        try {
            $gateway = $this->gatewayRegistry->get('message_queue');
            $gateway->reset('message_queue_stats', ElasticsearchIndexingMessage::class);
        } catch (IncrementGatewayNotFoundException) {
            // In case message_queue pool is disabled
        }

        $this->connection->executeStatement('DELETE FROM messenger_messages WHERE body LIKE "%ElasticsearchIndexingMessage%"');
    }

    private function matchesPrefix(string $indexName): bool
    {
        return str_starts_with($indexName, $this->indexPrefix . '_')
            || str_starts_with($indexName, $this->adminIndexPrefix . '-');
    }

    /**
     * @param array<string, array<mixed>> $indices
     * @param array<string, true> $activeIndexTasks
     *
     * @return list<string>
     */
    private function filterOrphanedIndices(array $indices, array $activeIndexTasks): array
    {
        $orphaned = [];

        foreach ($indices as $indexName => $config) {
            if (!$this->isOrphanedIndex($indexName, $config, $activeIndexTasks)) {
                continue;
            }

            $orphaned[] = $indexName;
        }

        return $orphaned;
    }

    /**
     * @param array<mixed> $config
     * @param array<string, true> $activeIndexTasks
     */
    private function isOrphanedIndex(string $indexName, array $config, array $activeIndexTasks): bool
    {
        if (!$this->matchesPrefix($indexName) || isset($activeIndexTasks[$indexName])) {
            return false;
        }

        $aliases = $config['aliases'] ?? null;

        return \is_array($aliases) && $aliases === [];
    }

    /**
     * @return array<string, true>
     */
    private function getActiveIndexTaskMap(): array
    {
        $activeIndexTasks = [];

        foreach (['elasticsearch_index_task', 'admin_elasticsearch_index_task'] as $table) {
            foreach ($this->connection->fetchFirstColumn(\sprintf('SELECT `index` FROM `%s`', $table)) as $index) {
                if (!\is_string($index) || $index === '') {
                    continue;
                }

                $activeIndexTasks[$index] = true;
            }
        }

        return $activeIndexTasks;
    }
}
