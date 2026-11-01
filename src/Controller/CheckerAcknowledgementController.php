<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Acl\FroshToolsPrivileges;
use Frosh\Tools\Components\AcknowledgementStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/api/_action/frosh-tools/checker/acknowledged-checks',
    defaults: ['_routeScope' => ['api'], '_acl' => [FroshToolsPrivileges::UPDATE]],
)]
class CheckerAcknowledgementController extends AbstractController
{
    public function __construct(private readonly AcknowledgementStore $acknowledgements)
    {
    }

    #[Route(
        path: '/{id}',
        name: 'api.frosh.tools.checker.acknowledged-checks.save',
        requirements: ['id' => '[A-Za-z0-9][A-Za-z0-9._-]{0,127}'],
        methods: ['PUT'],
    )]
    public function save(string $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $reason = \is_array($payload) ? ($payload['reason'] ?? null) : null;
        if (!\is_string($reason)) {
            return new JsonResponse(['error' => 'A reason is required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->acknowledgements->save($id, $reason);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'id' => $id,
            'acknowledgement' => trim($reason),
        ]);
    }

    #[Route(
        path: '/{id}',
        name: 'api.frosh.tools.checker.acknowledged-checks.revoke',
        requirements: ['id' => '[A-Za-z0-9][A-Za-z0-9._-]{0,127}'],
        methods: ['DELETE'],
    )]
    public function revoke(string $id): JsonResponse
    {
        try {
            $stillConfigured = $this->acknowledgements->revoke($id);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'id' => $id,
            'acknowledged' => $stillConfigured,
            'source' => $stillConfigured ? 'config' : null,
        ]);
    }
}
