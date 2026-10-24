<?php

declare(strict_types=1);

namespace Frosh\Tools\Controller;

use Frosh\Tools\Acl\FroshToolsPrivileges;
use Frosh\Tools\Components\Telemetry\PostHogClient;
use Frosh\Tools\Components\Telemetry\TelemetryCollector;
use Frosh\Tools\Components\Telemetry\TelemetryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/_action/frosh-tools', defaults: ['_routeScope' => ['api'], '_acl' => [FroshToolsPrivileges::TELEMETRY_READ]])]
class TelemetryController extends AbstractController
{
    public function __construct(private readonly TelemetryService $telemetryService)
    {
    }

    #[Route(path: '/telemetry', name: 'api.frosh.tools.telemetry.status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return new JsonResponse($this->buildStatus());
    }

    #[Route(path: '/telemetry/opt-in', name: 'api.frosh.tools.telemetry.opt-in', defaults: ['_acl' => [FroshToolsPrivileges::TELEMETRY_UPDATE]], methods: ['POST'])]
    public function setOptIn(Request $request): JsonResponse
    {
        $enabled = $request->request->get('enabled');
        if (!\is_bool($enabled)) {
            $payload = json_decode($request->getContent(), true);
            $enabled = \is_array($payload) ? ($payload['enabled'] ?? null) : null;
        }

        if (!\is_bool($enabled)) {
            return new JsonResponse(['error' => 'Expected a boolean "enabled" value.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->telemetryService->setEnabled($enabled);

        return new JsonResponse($this->buildStatus());
    }

    #[Route(path: '/telemetry/send', name: 'api.frosh.tools.telemetry.send', defaults: ['_acl' => [FroshToolsPrivileges::TELEMETRY_UPDATE]], methods: ['POST'])]
    public function sendNow(): JsonResponse
    {
        if (!$this->telemetryService->isEnabled()) {
            return new JsonResponse(['sent' => false, 'reason' => 'not-enabled']);
        }

        if (!$this->telemetryService->isPostHogConfigured()) {
            return new JsonResponse(['sent' => false, 'reason' => 'not-configured']);
        }

        $sent = $this->telemetryService->send();

        return new JsonResponse([
            'sent' => $sent,
            'reason' => $sent ? null : 'failed',
        ]);
    }

    #[Route(path: '/telemetry/instance-id', name: 'api.frosh.tools.telemetry.reset-instance-id', defaults: ['_acl' => [FroshToolsPrivileges::TELEMETRY_UPDATE]], methods: ['DELETE'])]
    public function resetInstanceId(): JsonResponse
    {
        $this->telemetryService->resetInstanceId();

        return new JsonResponse($this->buildStatus());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStatus(): array
    {
        return [
            'enabled' => $this->telemetryService->isEnabled(),
            'posthogConfigured' => $this->telemetryService->isPostHogConfigured(),
            'host' => PostHogClient::DEFAULT_HOST,
            'event' => PostHogClient::EVENT,
            'schemaVersion' => TelemetryCollector::SCHEMA_VERSION,
            'instanceId' => $this->telemetryService->getInstanceId(),
            'payload' => $this->telemetryService->preview(),
        ];
    }
}
