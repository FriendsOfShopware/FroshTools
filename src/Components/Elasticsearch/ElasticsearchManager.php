<?php declare(strict_types=1);

namespace Frosh\Tools\Components\Elasticsearch;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use Shopware\Core\Framework\Increment\Exception\IncrementGatewayNotFoundException;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;
use Symfony\Component\Messenger\MessageBusInterface;

class ElasticsearchManager
{
    protected ElasticsearchIndexer $indexer;
    protected MessageBusInterface $messageBus;
    protected CreateAliasTaskHandler $createAliasTaskHandler;
    protected ElasticsearchOutdatedIndexDetector $outdatedIndexDetector;
    protected Connection $connection;
    protected IncrementGatewayRegistry $gatewayRegistry;
    private Client $client;
    private bool $enabled;

    public function __construct(
        Client $client,
        bool $enabled,
        ElasticsearchIndexer $indexer,
        MessageBusInterface $messageBus,
        CreateAliasTaskHandler $createAliasTaskHandler,
        ElasticsearchOutdatedIndexDetector $outdatedIndexDetector,
        Connection $connection,
        IncrementGatewayRegistry $gatewayRegistry
    ) {
        $this->client = $client;
        $this->enabled = $enabled;
        $this->indexer = $indexer;
        $this->messageBus = $messageBus;
        $this->createAliasTaskHandler = $createAliasTaskHandler;
        $this->outdatedIndexDetector = $outdatedIndexDetector;
        $this->connection = $connection;
        $this->gatewayRegistry = $gatewayRegistry;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function info(): array
    {
        return [
            'info' => $this->client->info(),
            'health' => $this->client->cluster()->health(),
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

    public function proxy(string $method, string $path, array $params, array $body): array
    {
        if ($body === []) {
            $body = null;
        }

        $response = $this->client->transport->performRequest($method, $path, $params, $body);

        return $this->client->transport->resultOrFuture($response);
    }

    public function flushAll(): void
    {
        $this->client->indices()->flushSynced();
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
        $indices = $this->outdatedIndexDetector->get();

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
        } catch (IncrementGatewayNotFoundException $exception) {
            // In case message_queue pool is disabled
        }

        $this->connection->executeStatement('DELETE FROM enqueue WHERE body LIKE "%ElasticsearchIndexingMessage%"');
    }
}
