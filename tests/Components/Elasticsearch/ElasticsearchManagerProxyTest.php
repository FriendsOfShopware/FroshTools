<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Elasticsearch;

use Frosh\Tools\Components\Elasticsearch\ElasticsearchManager;
use OpenSearch\Client;
use OpenSearch\Transport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ElasticsearchManager::class)]
class ElasticsearchManagerProxyTest extends TestCase
{
    public function testProxyForwardsRequestAndNormalizesEmptyBody(): void
    {
        $client = $this->createMock(Client::class);
        $expected = ['acknowledged' => true];

        if (method_exists($client, 'request')) {
            $client->expects(static::once())->method('request')
                ->with('GET', '/_cluster/health', ['params' => ['pretty' => true], 'body' => null])
                ->willReturn($expected);
        } else {
            $transport = $this->createMock(Transport::class);
            $future = $this->createMock(\GuzzleHttp\Ring\Future\FutureArrayInterface::class);
            $transport->expects(static::once())->method('performRequest')
                ->with('GET', '/_cluster/health', ['pretty' => true], null)
                ->willReturn($future);
            $transport->expects(static::once())->method('resultOrFuture')->with($future)->willReturn($expected);
            $client->transport = $transport;
        }

        $reflection = new \ReflectionClass(ElasticsearchManager::class);
        $manager = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('client')->setValue($manager, $client);

        static::assertSame($expected, $manager->proxy('GET', '/_cluster/health', ['pretty' => true], []));
    }
}
