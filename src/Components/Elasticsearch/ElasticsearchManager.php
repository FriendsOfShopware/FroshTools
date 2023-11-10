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
        #[Autowire('%frosh_tools.elasticsearch.enabled%')]
        private readonly bool $enabled,
        private readonly ElasticsearchIndexer $indexer,
        private readonly MessageBusInterface $messageBus,
        private readonly CreateAliasTaskHandler $createAliasTaskHandler,
        private readonly ElasticsearchOutdatedIndexDetector $outdatedIndexDetector,
        private readonly Connection $connection,
        #[Autowire(service: 'shopware.increment.gateway.registry')]
        private readonly IncrementGatewayRegistry $gatewayRegistry
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

    /**
     * @return array<mixed>
     */
    public function deleteIndex(string $name): array
    {
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

    public function deleteUnusedIndices(): void
    {
        $indices = $this->outdatedIndexDetector->get() ?? [];

        foreach ($indices as $index) {
            $this->client->indices()->delete(['index' => $index]);
        }
    }

    public function reset(): void
    {
        $indices = $this->outdatedIndexDetector->getAllUsedIndices();

        foreach ($indices as $index) {
            $this->client->indices()->delete(['index' => $index]);
        }

        $this->connection->executeStatement('TRUNCATE elasticsearch_index_task');

        try {
            $gateway = $this->gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);
            $gateway->reset('message_queue_stats', ElasticsearchIndexingMessage::class);
        } catch (IncrementGatewayNotFoundException) {
            // In case message_queue pool is disabled
        }

        $this->connection->executeStatement('DELETE FROM messenger_messages WHERE body LIKE "%ElasticsearchIndexingMessage%"');
    }
}
