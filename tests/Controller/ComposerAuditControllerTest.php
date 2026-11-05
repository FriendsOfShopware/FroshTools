<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Components\ComposerAudit\ComposerAuditService;
use Frosh\Tools\Components\Security\Checker\DependencyAuditChecker;
use Frosh\Tools\Controller\ComposerAuditController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ComposerAuditController::class)]
final class ComposerAuditControllerTest extends TestCase
{
    public function testAuditKeepsAnAcknowledgedAdvisoryVisibleWithItsReason(): void
    {
        $advisory = [
            'packageName' => 'guzzlehttp/guzzle',
            'installedVersion' => '7.10.0',
            'advisoryId' => 'PKSA-1',
            'title' => 'Example',
            'severity' => 'high',
        ];
        $audit = $this->createStub(ComposerAuditService::class);
        $audit->method('audit')->willReturn([
            'packages' => 1,
            'vulnerable' => 1,
            'advisories' => [$advisory],
        ]);

        $controller = new ComposerAuditController($audit, [
            DependencyAuditChecker::findingId($advisory) => 'Reviewed the advisory',
        ]);

        $response = $controller->audit(new Request());
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(DependencyAuditChecker::findingId($advisory), $payload['advisories'][0]['id']);
        static::assertSame('Reviewed the advisory', $payload['advisories'][0]['acknowledgement']);
        static::assertSame('high', $payload['advisories'][0]['severity']);
    }
}
