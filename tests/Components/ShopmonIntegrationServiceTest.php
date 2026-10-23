<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components;

use Frosh\Tools\Acl\FroshToolsPrivileges;
use Frosh\Tools\Components\ShopmonIntegrationService;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(ShopmonIntegrationService::class)]
class ShopmonIntegrationServiceTest extends IntegrationTestCase
{
    private const ID = 'c2f0a1d4b6e84f309a5d7c1e8b2f4a60';
    private const LEGACY_ID = 'a1b2c3d4e5f6789012345678901234ab';
    private const CONFIG = 'FroshTools.config.shopmonIntegrationData';
    private const LEGACY_CONFIG = 'FroshShopmon.config.integrationData';

    private ShopmonIntegrationService $service;
    private array $serverBackup;
    private array $envBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->envBackup = $_ENV;
        unset($_SERVER['SHOPMON_ACCESS_KEY'], $_SERVER['SHOPMON_ACCESS_SECRET'], $_ENV['SHOPMON_ACCESS_KEY'], $_ENV['SHOPMON_ACCESS_SECRET']);

        $this->service = new ShopmonIntegrationService(
            static::getContainer()->get('integration.repository'),
            static::getContainer()->get('acl_role.repository'),
            static::getContainer()->get(SystemConfigService::class),
        );
        $this->service->remove(Context::createDefaultContext());
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_ENV = $this->envBackup;
        parent::tearDown();
    }

    public function testAdoptsLegacyIntegrationAndRefreshesPermissionsWithoutRotatingCredentials(): void
    {
        $context = Context::createDefaultContext();
        $accessKey = AccessKeyHelper::generateAccessKey('integration');
        $secret = AccessKeyHelper::generateSecretAccessKey();
        $repository = static::getContainer()->get('integration.repository');
        $repository->create([[
            'id' => self::LEGACY_ID,
            'label' => 'FroshShopmon Integration',
            'accessKey' => $accessKey,
            'secretAccessKey' => $secret,
            'aclRoles' => [[
                'id' => self::LEGACY_ID,
                'name' => 'frosh_shopmon_role',
                'privileges' => ['frosh_tools:read'],
            ]],
        ]], $context);
        $encoded = base64_encode(json_encode([
            'url' => 'https://shop.example',
            'clientId' => $accessKey,
            'clientSecret' => $secret,
        ], \JSON_THROW_ON_ERROR));
        $config = static::getContainer()->get(SystemConfigService::class);
        $config->set(self::LEGACY_CONFIG, $encoded);
        $before = $repository->search(new Criteria([self::LEGACY_ID]), $context)->getEntities()->first();
        static::assertNotNull($before);

        // Environment changes must not replace an integration already in use.
        $_SERVER['SHOPMON_ACCESS_KEY'] = AccessKeyHelper::generateAccessKey('integration');
        $_SERVER['SHOPMON_ACCESS_SECRET'] = 'another-secret';
        $this->service->initialize($context);
        $this->service->setup($context);

        $integrations = $repository->search(new Criteria([self::ID, self::LEGACY_ID]), $context)->getEntities();
        static::assertCount(1, $integrations);
        $integration = $integrations->get(self::LEGACY_ID);
        static::assertNotNull($integration);
        static::assertSame($accessKey, $integration->getAccessKey());
        static::assertSame($before->getSecretAccessKey(), $integration->getSecretAccessKey());
        static::assertSame($encoded, $config->getString(self::CONFIG));
        static::assertSame($encoded, $this->service->status($context)['integrationData']);

        $role = static::getContainer()->get('acl_role.repository')->search(new Criteria([self::LEGACY_ID]), $context)->getEntities()->first();
        static::assertNotNull($role);
        foreach ([FroshToolsPrivileges::APPS_READ, FroshToolsPrivileges::CACHE_UPDATE, FroshToolsPrivileges::SCHEDULED_TASK_UPDATE, FroshToolsPrivileges::SECURITY_READ, 'scheduled_task:update'] as $privilege) {
            static::assertContains($privilege, $role->getPrivileges());
        }

        $this->service->remove($context);
        static::assertSame(['configured' => false], $this->service->status($context));
        static::assertSame('', $config->getString(self::LEGACY_CONFIG));
        static::assertCount(0, $repository->search(new Criteria([self::ID, self::LEGACY_ID]), $context)->getEntities());
        static::assertCount(0, static::getContainer()->get('acl_role.repository')->search(new Criteria([self::LEGACY_ID]), $context)->getEntities());
    }

    public function testInitializePreservesExistingFroshToolsCredentialsAndUpdatesRole(): void
    {
        $context = Context::createDefaultContext();
        $this->service->setup($context);
        $before = $this->service->status($context);
        static::getContainer()->get('acl_role.repository')->update([[
            'id' => self::ID,
            'privileges' => ['frosh_tools:read'],
        ]], $context);

        $this->service->initialize($context);

        static::assertSame($before, $this->service->status($context));
        $role = static::getContainer()->get('acl_role.repository')->search(new Criteria([self::ID]), $context)->getEntities()->first();
        static::assertNotNull($role);
        static::assertContains(FroshToolsPrivileges::CACHE_UPDATE, $role->getPrivileges());
    }

    #[DataProvider('environmentCredentials')]
    public function testAutomaticCreationRequiresBothEnvironmentCredentials(string $accessKey, string $secret, bool $configured): void
    {
        $_SERVER['SHOPMON_ACCESS_KEY'] = $accessKey;
        $_SERVER['SHOPMON_ACCESS_SECRET'] = $secret;
        $context = Context::createDefaultContext();
        $this->service->initialize($context);
        $status = $this->service->status($context);
        static::assertSame($configured, $status['configured']);

        if ($configured) {
            static::assertSame($accessKey, $status['clientId']);
            static::assertSame($secret, $status['clientSecret']);
            $this->service->initialize($context);
            static::assertSame($status, $this->service->status($context));
        } else {
            static::assertCount(0, static::getContainer()->get('integration.repository')->search(new Criteria([self::ID]), $context)->getEntities());
        }
    }

    public static function environmentCredentials(): iterable
    {
        yield 'neither' => ['', '', false];
        yield 'key only' => ['SWIAABCDEFGHIJKLMNOPQRSTUVWX', '', false];
        yield 'secret only' => ['', 'shopmon-secret', false];
        yield 'both' => ['SWIAABCDEFGHIJKLMNOPQRSTUVWX', 'shopmon-secret', true];
    }

    public function testMalformedConfigurationDoesNotReportConfigured(): void
    {
        $context = Context::createDefaultContext();
        $this->service->setup($context);
        static::getContainer()->get(SystemConfigService::class)->set(self::CONFIG, base64_encode('{invalid'));
        static::assertSame(['configured' => false], $this->service->status($context));
    }
}
