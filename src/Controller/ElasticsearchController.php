<?php

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\Elasticsearch\ElasticsearchManager;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 * @Route(path="/api/_action/frosh-tools/elasticsearch")
 */
class ElasticsearchController
{
    private ElasticsearchManager $manager;

    public function __construct(ElasticsearchManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * @Route(path="/status", methods={"GET"}, name="api.frosh.tools.elasticsearch.status")
     */
    public function status(): Response
    {
        if (!$this->manager->isEnabled()) {
            return new Response('', Response::HTTP_PRECONDITION_FAILED);
        }

        return new JsonResponse($this->manager->info());
    }

    /**
     * @Route(path="/indices", methods={"GET"}, name="api.frosh.tools.elasticsearch.indices")
     */
    public function indices(): Response
    {
        if (!$this->manager->isEnabled()) {
            return new Response('', Response::HTTP_PRECONDITION_FAILED);
        }

        return new JsonResponse($this->manager->indices());
    }

    /**
     * @Route(path="/index/{indexName}", methods={"DELETE"}, name="api.frosh.tools.elasticsearch.delete_index")
     */
    public function deleteIndex(string $indexName): Response
    {
        if (!$this->manager->isEnabled()) {
            return new Response('', Response::HTTP_PRECONDITION_FAILED);
        }

        return new JsonResponse($this->manager->deleteIndex($indexName));
    }
}
