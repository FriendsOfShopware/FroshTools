<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Components\AcknowledgementStore;
use Frosh\Tools\Components\ComposerAudit\ComposerAuditService;
use Frosh\Tools\Components\Security\Checker\DependencyAuditChecker;
use Frosh\Tools\Controller\ComposerAuditController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

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

        $store = $this->store();
        $store->save(DependencyAuditChecker::findingId($advisory), 'Reviewed the advisory');
        $controller = new ComposerAuditController($audit, $store);

        $response = $controller->audit(new Request());
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(DependencyAuditChecker::findingId($advisory), $payload['advisories'][0]['id']);
        static::assertSame('Reviewed the advisory', $payload['advisories'][0]['acknowledgement']);
        static::assertSame('high', $payload['advisories'][0]['severity']);
    }

    private function store(): AcknowledgementStore
    {
        $stored = new \stdClass();
        $stored->value = null;
        $config = $this->createStub(SystemConfigService::class);
        $config->method('get')->willReturnCallback(static fn (): mixed => $stored->value);
        $config->method('set')->willReturnCallback(static function (string $key, mixed $value) use ($stored): void {
            $stored->value = $value;
        });

        return new AcknowledgementStore($config, [], new ArrayAdapter(), new LockFactory(new InMemoryStore()));
    }
}
