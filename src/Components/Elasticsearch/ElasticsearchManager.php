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
    ) {
    }

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
        $indices = $this->client->indices()->get(['index' => '*']);
        $stats = $this->client->indices()->stats(['index' => '*']);

        $list = [];

        foreach ($indices as $indexName => $config) {
            if (!$this->matchesPrefix($indexName)) {
                continue;
            }

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

    /**
     * @return array<mixed>
     */
    public function deleteIndex(string $name): array
    {
        if (!$this->matchesPrefix($name)) {
            throw new \InvalidArgumentException(\sprintf('Index "%s" does not match the configured prefix', $name));
        }

        return $this->client->indices()->delete(['index' => $name]);
    }

    /**
     * @param array<mixed> $body
     * @param array<mixed> $params
     *
     * @return array<mixed>|callable
     */
    public function proxy(string $method, string $path, array $params, array $body): array|callable
    {
        if ($body === []) {
            $body = null;
        }

        $response = $this->client->transport->performRequest($method, $path, $params, $body);

        return $this->client->transport->resultOrFuture($response);
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
     * Safety net: any index that has at least one alias is excluded — Shopware
     * relies on aliases to route to the live indices.
     *
     * @return array{indices: array<string>, error: string|null}
     */
    public function getOrphanedIndices(): array
    {
        try {
            $indices = $this->client->indices()->get(['index' => '*']);
        } catch (\Throwable $e) {
            return ['indices' => [], 'error' => $e->getMessage()];
        }

        $orphaned = [];
        foreach ($indices as $indexName => $config) {
            if (!$this->matchesPrefix($indexName)) {
                continue;
            }

            $aliases = $config['aliases'] ?? [];
            if ($aliases !== []) {
                continue;
            }

            $orphaned[] = $indexName;
        }

        return ['indices' => $orphaned, 'error' => null];
    }

    /**
     * @return array{deleted: array<string>, errors: array<string, string>}
     */
    public function deleteOrphanedIndices(): array
    {
        $result = ['deleted' => [], 'errors' => []];

        $orphaned = $this->getOrphanedIndices();
        if ($orphaned['error'] !== null) {
            $result['errors']['__detector__'] = $orphaned['error'];

            return $result;
        }

        foreach ($orphaned['indices'] as $index) {
            // Defensive double-check: never delete anything outside the
            // configured prefix, even if upstream lookups regress.
            if (!$this->matchesPrefix($index)) {
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
}
