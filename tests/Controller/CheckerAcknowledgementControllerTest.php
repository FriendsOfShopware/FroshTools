<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Components\AcknowledgementStore;
use Frosh\Tools\Controller\CheckerAcknowledgementController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(CheckerAcknowledgementController::class)]
final class CheckerAcknowledgementControllerTest extends TestCase
{
    public function testSaveRequiresAReasonAndThenRevokeClearsIt(): void
    {
        $controller = new CheckerAcknowledgementController($this->store());

        $missing = $controller->save('composer-audit', new Request(content: '{"reason":"  "}'));
        static::assertSame(400, $missing->getStatusCode());

        $saved = $controller->save('composer-audit', new Request(content: '{"reason":"Reviewed the advisory"}'));
        static::assertSame(200, $saved->getStatusCode());
        static::assertSame(
            ['id' => 'composer-audit', 'acknowledgement' => 'Reviewed the advisory'],
            json_decode((string) $saved->getContent(), true, 512, \JSON_THROW_ON_ERROR),
        );

        $revoked = $controller->revoke('composer-audit');
        static::assertSame(
            ['id' => 'composer-audit', 'acknowledged' => false, 'source' => null],
            json_decode((string) $revoked->getContent(), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    public function testRevokeReportsAFileEntryThatStaysInPlace(): void
    {
        $controller = new CheckerAcknowledgementController($this->store([
            'php-version' => 'from the file',
        ]));

        $response = $controller->revoke('php-version');

        static::assertSame(
            ['id' => 'php-version', 'acknowledged' => true, 'source' => 'config'],
            json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param array<string, string> $configured
     */
    private function store(array $configured = []): AcknowledgementStore
    {
        $stored = new \stdClass();
        $stored->value = null;
        $config = $this->createStub(SystemConfigService::class);
        $config->method('get')->willReturnCallback(static fn (): mixed => $stored->value);
        $config->method('set')->willReturnCallback(static function (string $key, mixed $value) use ($stored): void {
            $stored->value = $value;
        });

        return new AcknowledgementStore($config, $configured, new ArrayAdapter());
    }
}
