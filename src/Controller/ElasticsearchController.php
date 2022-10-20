<?php declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Components\Elasticsearch\ElasticsearchManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/api/_action/frosh-tools/elasticsearch", defaults={"_routeScope"={"api"}, "_acl"={"frosh_tools:read"}})
 */
class ElasticsearchController
{
    private ElasticsearchManager $manager;

    public function __construct(ElasticsearchManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * @Route(path="/status", methods={"GET"}, name="api.frosh.tools.elasticsearch.status", defaults={"_acl"={"frosh_tools:read"}})
     */
    public function status(): Response
    {
        if (!$this->manager->isEnabled()) {
            return new Response('', Response::HTTP_PRECONDITION_FAILED);
        }

        return new JsonResponse($this->manager->info());
    }

    /**
     * @Route(path="/indices", methods={"GET"}, name="api.frosh.tools.elasticsearch.indices", defaults={"_acl"={"frosh_tools:read"}})
     */
    public function indices(): Response
    {
        if (!$this->manager->isEnabled()) {
            return new Response('', Response::HTTP_PRECONDITION_FAILED);
        }

        return new JsonResponse($this->manager->indices());
    }

    /**
     * @Route(path="/index/{indexName}", methods={"DELETE"}, name="api.frosh.tools.elasticsearch.delete_index", defaults={"_acl"={"frosh_tools:read"}})
     */
    public function deleteIndex(string $indexName): Response
    {
        if (!$this->manager->isEnabled()) {
            return new Response('', Response::HTTP_PRECONDITION_FAILED);
        }

        return new JsonResponse($this->manager->deleteIndex($indexName));
    }

    /**
     * @Route(path="/console/{path}", name="api.frosh.tools.elasticsearch.proxy", requirements={"path" = ".*"}, defaults={"_acl"={"frosh_tools:read"}})
     */
    public function console(Request $request, string $path): Response
    {
        if (!$this->manager->isEnabled()) {
            return new Response('', Response::HTTP_PRECONDITION_FAILED);
        }

        $data = $this->manager->proxy($request->getMethod(), '/' . $path, $request->query->all(), $request->request->all());

        return new JsonResponse($data);
    }

    /**
     * @Route(path="/flush_all", methods={"POST"}, name="api.frosh.tools.elasticsearch.flush", defaults={"_acl"={"frosh_tools:read"}})
     */
    public function flushAll(): Response
    {
        $this->manager->flushAll();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route(path="/reindex", methods={"POST"}, name="api.frosh.tools.elasticsearch.reindex", defaults={"_acl"={"frosh_tools:read"}})
     */
    public function reindex(): Response
    {
        $this->manager->reindex();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route(path="/switch_alias", methods={"POST"}, name="api.frosh.tools.elasticsearch.switch_alias", defaults={"_acl"={"frosh_tools:read"}})
     */
    public function switchAlias(): Response
    {
        $this->manager->switchAlias();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route(path="/cleanup", methods={"POST"}, name="api.frosh.tools.elasticsearch.cleanup", defaults={"_acl"={"frosh_tools:read"}})
     */
    public function deleteUnusedIndices(): Response
    {
        $this->manager->deleteUnusedIndices();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route(path="/reset", methods={"POST"}, name="api.frosh.tools.elasticsearch.reset", defaults={"_acl"={"frosh_tools:read"}})
     */
    public function reset(): Response
    {
        $this->manager->reset();

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
