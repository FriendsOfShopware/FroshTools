<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components;

use Frosh\Tools\Components\AcknowledgementStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(AcknowledgementStore::class)]
final class AcknowledgementStoreTest extends TestCase
{
    public function testAdministrationValueOverridesTheFileAndRevokeKeepsTheFileEntry(): void
    {
        $stored = new \stdClass();
        $stored->value = null;
        $config = $this->createStub(SystemConfigService::class);
        $config->method('get')->willReturnCallback(static fn (): mixed => $stored->value);
        $config->method('set')->willReturnCallback(static function (string $key, mixed $value) use ($stored): void {
            TestCase::assertSame(AcknowledgementStore::CONFIG_KEY, $key);
            $stored->value = $value;
        });

        $store = new AcknowledgementStore($config, [
            'php-version' => 'from the file',
        ], new ArrayAdapter(), self::locks());

        static::assertSame(['php-version' => 'from the file'], $store->all());

        $store->save('php-version', 'from the administration');
        static::assertSame(['php-version' => 'from the administration'], $store->all());

        static::assertTrue($store->revoke('php-version'));
        static::assertSame(['php-version' => 'from the file'], $store->all());
    }

    public function testRevokeRemovesAnAdministrationOnlyEntry(): void
    {
        $stored = new \stdClass();
        $stored->value = null;
        $config = $this->createStub(SystemConfigService::class);
        $config->method('get')->willReturnCallback(static fn (): mixed => $stored->value);
        $config->method('set')->willReturnCallback(static function (string $key, mixed $value) use ($stored): void {
            $stored->value = $value;
        });

        $store = new AcknowledgementStore($config, [], new ArrayAdapter(), self::locks());
        $store->save('composer-audit', 'Reviewed the advisory');

        static::assertFalse($store->revoke('composer-audit'));
        static::assertSame([], $store->all());
    }

    public function testEmptyReasonIsRejected(): void
    {
        $store = new AcknowledgementStore($this->createStub(SystemConfigService::class), [], new ArrayAdapter(), self::locks());

        $this->expectException(\InvalidArgumentException::class);
        $store->save('php-version', '   ');
    }

    public function testASecondSaveKeepsTheFirstConfirmation(): void
    {
        $stored = new \stdClass();
        $stored->value = null;
        $config = $this->createStub(SystemConfigService::class);
        $config->method('get')->willReturnCallback(static fn (): mixed => $stored->value);
        $config->method('set')->willReturnCallback(static function (string $key, mixed $value) use ($stored): void {
            $stored->value = $value;
        });

        $store = new AcknowledgementStore($config, [], new ArrayAdapter(), self::locks());
        $store->save('php-version', 'Reviewed the package');
        $store->save('composer-audit', 'Reviewed the advisory');

        static::assertSame([
            'php-version' => 'Reviewed the package',
            'composer-audit' => 'Reviewed the advisory',
        ], $store->all());
    }

    private static function locks(): LockFactory
    {
        return new LockFactory(new InMemoryStore());
    }
}
