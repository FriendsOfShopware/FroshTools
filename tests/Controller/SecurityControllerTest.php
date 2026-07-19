<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Controller\SecurityController;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(SecurityController::class)]
class SecurityControllerTest extends IntegrationTestCase
{
    private SecurityController $controller;

    protected function setUp(): void
    {
        $this->controller = static::getContainer()->get(SecurityController::class);
    }

    public function testStatusReturnsSummaryAndFindings(): void
    {
        $response = $this->controller->status();

        static::assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        static::assertIsArray($data);
        static::assertArrayHasKey('summary', $data);
        static::assertArrayHasKey('findings', $data);

        static::assertIsArray($data['summary']);
        foreach (['critical', 'high', 'medium', 'low', 'unknown', 'ok'] as $severity) {
            static::assertArrayHasKey($severity, $data['summary']);
            static::assertIsInt($data['summary'][$severity]);
        }

        static::assertIsArray($data['findings']);
        foreach ($data['findings'] as $finding) {
            static::assertIsArray($finding);
            static::assertArrayHasKey('id', $finding);
            static::assertArrayHasKey('severity', $finding);
            static::assertArrayHasKey('category', $finding);
        }
    }

    public function testSbomReturnsCycloneDxJsonAttachment(): void
    {
        $response = $this->controller->sbom(new Request());

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('application/vnd.cyclonedx+json', $response->headers->get('Content-Type'));

        $contentDisposition = $response->headers->get('Content-Disposition');
        static::assertNotNull($contentDisposition);
        static::assertStringContainsString('attachment', $contentDisposition);

        $bom = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        static::assertIsArray($bom);
        static::assertSame('CycloneDX', $bom['bomFormat'] ?? null);
    }

    public function testSbomWithIncludeDevIncludesDevPackages(): void
    {
        $withoutDev = json_decode((string) $this->controller->sbom(new Request())->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $withDev = json_decode((string) $this->controller->sbom(new Request(['includeDev' => '1']))->getContent(), true, 512, JSON_THROW_ON_ERROR);

        static::assertIsArray($withDev);
        static::assertSame('CycloneDX', $withDev['bomFormat'] ?? null);

        static::assertIsArray($withoutDev['components'] ?? null);
        static::assertIsArray($withDev['components'] ?? null);
        static::assertNotEmpty($withDev['components']);
        static::assertGreaterThanOrEqual(\count($withoutDev['components']), \count($withDev['components']));
    }
}
