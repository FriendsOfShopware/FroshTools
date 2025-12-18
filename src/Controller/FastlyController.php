<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route(path: '/api/_action/frosh-tools/fastly', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class FastlyController extends AbstractController
{
    public function __construct(
        #[Autowire(param: 'shopware.http_cache.reverse_proxy.fastly.service_id')]
        private readonly ?string $serviceId = null,
        #[Autowire(param: 'shopware.http_cache.reverse_proxy.fastly.api_key')]
        private readonly ?string $apiKey = null,
        private readonly ?AbstractReverseProxyGateway $reverseProxyGateway = null,
        private readonly ?HttpClientInterface $client = null,
    ) {
    }

    #[Route(path: '/status', name: 'api.frosh.tools.fastly.status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return new JsonResponse(['enabled' => $this->reverseProxyGateway !== null]);
    }

    #[Route(path: '/statistics', name: 'api.frosh.tools.fastly.statistics', methods: ['GET'])]
    public function statistics(Request $request): JsonResponse
    {
        if (!$this->client || !$this->isFastlyEnabled()) {
            return new JsonResponse(['message' => 'Fastly is not enabled'], Response::HTTP_BAD_REQUEST);
        }

        $timeframe = $request->query->get('timeframe', '2h');

        $diff = match ($timeframe) {
            '30m' => 1800,
            '1h' => 3600,
            '24h' => 86400,
            '7d' => 604800,
            '30d' => 2592000,
            default => 7200,
        };

        $response = $this->client->request('GET', \sprintf('https://api.fastly.com/service/%s/stats/summary', $this->serviceId), [
            'headers' => [
                'Fastly-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ],
            'query' => [
                'start_time' => (string) (time() - $diff),
                'end_time' => (string) time(),
                'by' => 'minute',
            ],
        ]);

        $data = $response->toArray();

        $aggregated = [
            'requests' => 0,
            'hits' => 0,
            'bandwidth' => 0,
            'hit_ratio' => 0.0,
        ];

        foreach ($data['stats'] as $item) {
            $aggregated['requests'] += $item['requests'] ?? 0;
            $aggregated['hits'] += $item['hits'] ?? 0;
            $aggregated['bandwidth'] += $item['req_body_bytes'] + $item['req_header_bytes'] + $item['body_size'];
        }

        if ($aggregated['requests'] > 0) {
            $aggregated['hit_ratio'] = round($aggregated['hits'] / $aggregated['requests'], 4);
        }

        return new JsonResponse($aggregated);
    }

    #[Route(path: '/snippets', name: 'api.frosh.tools.fastly.snippets', methods: ['GET'])]
    public function snippets(): JsonResponse
    {
        if (!$this->client || !$this->isFastlyEnabled()) {
            return new JsonResponse(['message' => 'Fastly is not enabled'], Response::HTTP_BAD_REQUEST);
        }

        $response = $this->client->request('GET', \sprintf('https://api.fastly.com/service/%s', $this->serviceId), [
            'headers' => [
                'Fastly-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ],
        ]);

        $content = $response->toArray();

        $activeVersion = null;
        foreach ($content['versions'] ?? [] as $version) {
            if ($version['active']) {
                $activeVersion = $version['number'];
                break;
            }
        }

        if (!$activeVersion) {
            return new JsonResponse(['message' => 'No active version found'], Response::HTTP_NOT_FOUND);
        }

        $response = $this->client->request('GET', \sprintf('https://api.fastly.com/service/%s/version/%s/snippet', $this->serviceId, $activeVersion), [
            'headers' => [
                'Fastly-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ],
        ]);

        return new JsonResponse($response->toArray());
    }

    #[Route(path: '/purge', name: 'api.frosh.tools.fastly.purge', methods: ['POST'])]
    public function purge(Request $request): JsonResponse
    {
        if (!$this->reverseProxyGateway || !$this->isFastlyEnabled()) {
            return new JsonResponse(['message' => 'Fastly is not enabled'], Response::HTTP_BAD_REQUEST);
        }

        $path = $request->request->get('path');

        if (!is_string($path) || empty($path)) {
            return new JsonResponse(['message' => 'Path is required'], Response::HTTP_BAD_REQUEST);
        }

        $this->reverseProxyGateway->ban([$path]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/purge-all', name: 'api.frosh.tools.fastly.purge_all', methods: ['POST'])]
    public function purgeAll(): JsonResponse
    {
        if (!$this->reverseProxyGateway || !$this->isFastlyEnabled()) {
            return new JsonResponse(['message' => 'Fastly is not enabled'], Response::HTTP_BAD_REQUEST);
        }

        $this->reverseProxyGateway->banAll();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function isFastlyEnabled(): bool
    {
        return $this->reverseProxyGateway !== null
            && $this->serviceId
            && $this->apiKey
            && $this->client !== null;
    }
}
