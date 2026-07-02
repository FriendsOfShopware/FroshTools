<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\Elasticsearch\ElasticsearchManager;
use Frosh\Tools\Components\Exception\FroshToolsException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/_action/frosh-tools/elasticsearch', defaults: ['_routeScope' => ['api'], '_acl' => ['frosh_tools:read']])]
class ElasticsearchController extends AbstractController
{
    public function __construct(private readonly ElasticsearchManager $manager) {}

    #[Route(path: '/status', name: 'api.frosh.tools.elasticsearch.status', methods: ['GET'])]
    public function status(): Response
    {
        if (!$this->manager->isEnabled()) {
            throw FroshToolsException::elasticsearchDisabled();
        }

        return new JsonResponse($this->manager->info());
    }

    #[Route(path: '/indices', name: 'api.frosh.tools.elasticsearch.indices', methods: ['GET'])]
    public function indices(): Response
    {
        if (!$this->manager->isEnabled()) {
            throw FroshToolsException::elasticsearchDisabled();
        }

        return new JsonResponse($this->manager->indices());
    }

    #[Route(path: '/index/{indexName}', name: 'api.frosh.tools.elasticsearch.delete_index', methods: ['DELETE'])]
    public function deleteIndex(string $indexName): Response
    {
        if (!$this->manager->isEnabled()) {
            throw FroshToolsException::elasticsearchDisabled();
        }

        return new JsonResponse($this->manager->deleteIndex($indexName));
    }

    #[Route(path: '/console/{path}', name: 'api.frosh.tools.elasticsearch.proxy', requirements: ['path' => '.*'])]
    public function console(Request $request, string $path): Response
    {
        if (!$this->manager->isEnabled()) {
            throw FroshToolsException::elasticsearchDisabled();
        }

        $body = $request->request->all();
        $content = trim($request->getContent());

        if ($body === [] && $content !== '') {
            $decoded = json_decode($content, true);
            $body = \json_last_error() === \JSON_ERROR_NONE ? $decoded : $content;
        }

        $data = $this->manager->proxy($request->getMethod(), '/' . $path, $request->query->all(), $body);

        return new JsonResponse($data);
    }

    #[Route(path: '/flush_all', name: 'api.frosh.tools.elasticsearch.flush', methods: ['POST'])]
    public function flushAll(): Response
    {
        $this->manager->flushAll();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/reindex', name: 'api.frosh.tools.elasticsearch.reindex', methods: ['POST'])]
    public function reindex(): Response
    {
        $this->manager->reindex();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/switch_alias', name: 'api.frosh.tools.elasticsearch.switch_alias', methods: ['POST'])]
    public function switchAlias(): Response
    {
        $this->manager->switchAlias();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(path: '/unused_indices', name: 'api.frosh.tools.elasticsearch.unused_indices', methods: ['GET'])]
    public function unusedIndices(): Response
    {
        if (!$this->manager->isEnabled()) {
            throw FroshToolsException::elasticsearchDisabled();
        }

        return new JsonResponse($this->manager->getUnusedIndices());
    }

    #[Route(path: '/orphaned_indices', name: 'api.frosh.tools.elasticsearch.orphaned_indices', methods: ['GET'])]
    public function orphanedIndices(): Response
    {
        if (!$this->manager->isEnabled()) {
            throw FroshToolsException::elasticsearchDisabled();
        }

        return new JsonResponse($this->manager->getOrphanedIndices());
    }

    #[Route(path: '/cleanup', name: 'api.frosh.tools.elasticsearch.cleanup', methods: ['POST'])]
    public function deleteUnusedIndices(): Response
    {
        if (!$this->manager->isEnabled()) {
            throw FroshToolsException::elasticsearchDisabled();
        }

        return new JsonResponse($this->manager->deleteUnusedIndices());
    }

    #[Route(path: '/cleanup_orphaned', name: 'api.frosh.tools.elasticsearch.cleanup_orphaned', methods: ['POST'])]
    public function deleteOrphanedIndices(Request $request): Response
    {
        if (!$this->manager->isEnabled()) {
            throw FroshToolsException::elasticsearchDisabled();
        }

        $data = $request->request->all();
        $content = trim($request->getContent());

        if ($data === [] && $content !== '') {
            try {
                $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }

            if (!\is_array($decoded)) {
                return new JsonResponse(['message' => 'Request body must be a JSON object.'], Response::HTTP_BAD_REQUEST);
            }

            $data = $decoded;
        }

        $indices = $data['indices'] ?? [];
        if (!\is_array($indices)) {
            return new JsonResponse(['message' => 'Field "indices" must be an array.'], Response::HTTP_BAD_REQUEST);
        }

        $indices = array_values(array_unique(array_filter($indices, static fn(mixed $index): bool => \is_string($index) && $index !== '')));

        return new JsonResponse($this->manager->deleteOrphanedIndices($indices));
    }

    #[Route(path: '/reset', name: 'api.frosh.tools.elasticsearch.reset', methods: ['POST'])]
    public function reset(): Response
    {
        $this->manager->reset();

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
