<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\AdminAccountService;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AdminAccountService::class)]
class AdminAccountServiceTest extends IntegrationTestCase
{
    /**
     * Fields that must never leave the shop. Guarding the payload itself keeps a
     * later "just add the username" change from silently leaking personal data.
     */
    private const FORBIDDEN_KEYS = [
        'username',
        'email',
        'firstName',
        'lastName',
        'title',
        'password',
        'accessKey',
        'secretAccessKey',
        'storeToken',
        'avatarId',
        'localeId',
    ];

    private AdminAccountService $accounts;

    protected function setUp(): void
    {
        $this->accounts = static::getContainer()->get(AdminAccountService::class);
    }

    public function testUsersExposeOnlyNonPersonalFields(): void
    {
        $users = $this->accounts->getUsers();

        static::assertNotEmpty($users, 'The test fixture ships at least one admin user');

        foreach ($users as $user) {
            foreach (self::FORBIDDEN_KEYS as $key) {
                static::assertArrayNotHasKey($key, $user);
            }

            static::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $user['id']);
            static::assertIsBool($user['active']);
            static::assertIsBool($user['admin']);
            static::assertIsBool($user['passwordRecoveryPending']);
            static::assertIsArray($user['aclRoleIds']);
            static::assertIsInt($user['accessKeyCount']);
            static::assertSame('/admin#/sw/users/permissions/user/detail/' . $user['id'], $user['adminPath']);
            static::assertNotNull($user['createdAt']);
        }
    }

    public function testIntegrationsExposeNoCredentials(): void
    {
        $integrations = $this->accounts->getIntegrations();

        foreach ($integrations as $integration) {
            foreach (self::FORBIDDEN_KEYS as $key) {
                static::assertArrayNotHasKey($key, $integration);
            }

            static::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $integration['id']);
            static::assertIsString($integration['label']);
            static::assertIsBool($integration['admin']);
            static::assertIsBool($integration['managedByApp']);
            static::assertSame($integration['appName'] !== null, $integration['managedByApp']);
            static::assertIsArray($integration['aclRoleIds']);
        }
    }

    public function testAppIntegrationReportsItsAppAclRole(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $integrationId = hex2bin(str_repeat('c1', 16));
        $appRoleId = hex2bin(str_repeat('c2', 16));
        $otherRoleId = hex2bin(str_repeat('c3', 16));

        $connection->insert('integration', [
            'id' => $integrationId,
            'label' => 'frosh_tools_app_test',
            'access_key' => 'frosh_tools_app_test_key',
            'secret_access_key' => 'frosh_tools_app_test_secret',
            'admin' => 0,
            'created_at' => '2024-01-01 00:00:00.000',
        ]);

        foreach ([$appRoleId => 'frosh_tools_app_role', $otherRoleId => 'frosh_tools_other_role'] as $id => $name) {
            $connection->insert('acl_role', [
                'id' => $id,
                'name' => $name,
                'privileges' => json_encode(['app:read'], \JSON_THROW_ON_ERROR),
                'created_at' => '2024-01-01 00:00:00.000',
            ]);
        }

        // Attached the way Shopware wires an app: through integration_role, which the
        // service must not mistake for the app's own role.
        $connection->insert('integration_role', [
            'integration_id' => $integrationId,
            'acl_role_id' => $otherRoleId,
        ]);

        $connection->insert('app', [
            'id' => hex2bin(str_repeat('c4', 16)),
            'name' => 'FroshToolsAppTest',
            'path' => 'custom/apps/FroshToolsAppTest',
            'version' => '1.0.0',
            'active' => 1,
            'configurable' => 0,
            'allow_disable' => 1,
            'integration_id' => $integrationId,
            'acl_role_id' => $appRoleId,
            'created_at' => '2024-01-01 00:00:00.000',
        ]);

        $integration = null;
        foreach ($this->accounts->getIntegrations() as $candidate) {
            if ($candidate['label'] === 'frosh_tools_app_test') {
                $integration = $candidate;
            }
        }

        static::assertNotNull($integration, 'App integration fixture not returned');
        static::assertTrue($integration['managedByApp']);
        static::assertSame('FroshToolsAppTest', $integration['appName']);

        // Apps grant permissions via app.acl_role_id, which Shopware resolves with
        // precedence over integration_role - that role must not go missing here.
        static::assertContains(bin2hex($appRoleId), $integration['aclRoleIds']);
        static::assertContains(bin2hex($otherRoleId), $integration['aclRoleIds']);
    }

    public function testAclRolesAreHashedInsteadOfListed(): void
    {
        $roles = $this->accounts->getAclRoles();

        foreach ($roles as $role) {
            static::assertArrayNotHasKey('privileges', $role);
            static::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $role['id']);
            static::assertIsString($role['name']);
            static::assertIsInt($role['privilegeCount']);
            static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $role['privilegesHash']);
        }
    }

    public function testPrivilegeHashIsStableAndOrderIndependent(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $id = hex2bin(str_repeat('ab', 16));

        $connection->insert('acl_role', [
            'id' => $id,
            'name' => 'frosh_tools_account_test',
            'privileges' => json_encode(['b:read', 'a:read'], \JSON_THROW_ON_ERROR),
            'created_at' => '2024-01-01 00:00:00.000',
        ]);

        $before = $this->findRole('frosh_tools_account_test');

        $connection->update(
            'acl_role',
            ['privileges' => json_encode(['a:read', 'b:read'], \JSON_THROW_ON_ERROR)],
            ['id' => $id],
        );

        $reordered = $this->findRole('frosh_tools_account_test');
        static::assertSame($before['privilegesHash'], $reordered['privilegesHash'], 'Reordering privileges must not look like a change');

        $connection->update(
            'acl_role',
            ['privileges' => json_encode(['a:read', 'b:read', 'c:write'], \JSON_THROW_ON_ERROR)],
            ['id' => $id],
        );

        $escalated = $this->findRole('frosh_tools_account_test');
        static::assertNotSame($before['privilegesHash'], $escalated['privilegesHash'], 'An added privilege must be detectable');
        static::assertSame(3, $escalated['privilegeCount']);
    }

    /**
     * @return array<string, mixed>
     */
    private function findRole(string $name): array
    {
        foreach ($this->accounts->getAclRoles() as $role) {
            if ($role['name'] === $name) {
                return $role;
            }
        }

        static::fail(\sprintf('Role "%s" not found', $name));
    }
}
