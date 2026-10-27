<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security;

use Frosh\Tools\Components\Security\AdminAccountService;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\Integration\IntegrationCollection;
use Shopware\Core\System\Integration\IntegrationDefinition;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

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
            static::assertMatchesRegularExpression('/^.\\*{3}@.\\*{3}(\\..+)?$/', (string) $user['maskedEmail']);
            static::assertIsBool($user['active']);
            static::assertIsBool($user['admin']);
            static::assertIsBool($user['passwordRecoveryPending']);
            static::assertIsArray($user['aclRoleIds']);
            static::assertIsInt($user['accessKeyCount']);
            static::assertSame('/admin#/sw/users/permissions/user/detail/' . $user['id'], $user['adminPath']);
            static::assertNotNull($user['createdAt']);
        }
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function emails(): iterable
    {
        yield 'company address' => ['admin@example.com', 'a***@e***.com'];
        yield 'long local part' => ['verylongfirstname.lastname@example.com', 'v***@e***.com'];
        yield 'single char local part' => ['a@example.com', 'a***@e***.com'];
        yield 'subdomain' => ['admin@mail.example.com', 'a***@m***.com'];
        yield 'multi part tld' => ['admin@example.co.uk', 'a***@e***.uk'];
        yield 'no tld' => ['admin@localhost', 'a***@l***'];
        yield 'plus addressing' => ['admin+shop@example.com', 'a***@e***.com'];
        yield 'empty' => ['', null];
        yield 'no at sign' => ['notanemail', null];
        yield 'nothing before at' => ['@example.com', null];
        yield 'nothing after at' => ['admin@', null];
    }

    #[DataProvider('emails')]
    public function testEmailMaskingHidesLengthAndContent(string $email, ?string $expected): void
    {
        $mask = new \ReflectionMethod(AdminAccountService::class, 'maskEmail');

        static::assertSame($expected, $mask->invoke($this->accounts, $email));
    }

    public function testMaskedEmailsOfDifferentLengthAreIndistinguishable(): void
    {
        $mask = new \ReflectionMethod(AdminAccountService::class, 'maskEmail');

        static::assertSame(
            $mask->invoke($this->accounts, 'a@example.com'),
            $mask->invoke($this->accounts, 'averylongadminaddress@example.com'),
            'The star count must not reveal the real length',
        );
    }

    public function testIntegrationsExposeNoCredentials(): void
    {
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();

        $this->createIntegration($ids->get('integration'), 'frosh_tools_credentials_test', $context);

        $integrations = $this->accounts->getIntegrations();
        static::assertNotEmpty($integrations, 'The fixture integration must be returned');

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
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();

        $this->createIntegration($ids->get('integration'), 'frosh_tools_app_test', $context);
        $this->createAclRoles([
            'appRole' => 'frosh_tools_app_role',
            'otherRole' => 'frosh_tools_other_role',
        ], $context, $ids);

        // Attached the way Shopware wires an app: through integration_role, which the
        // service must not mistake for the app's own role.
        $this->getRepository(IntegrationDefinition::ENTITY_NAME, IntegrationCollection::class)->update([[
            'id' => $ids->get('integration'),
            'aclRoles' => [['id' => $ids->get('otherRole')]],
        ]], $context);

        $this->getRepository(AppDefinition::ENTITY_NAME, AppCollection::class)->create([[
            'id' => $ids->get('app'),
            'name' => 'FroshToolsAppTest',
            'label' => 'FroshToolsAppTest',
            'path' => 'custom/apps/FroshToolsAppTest',
            'version' => '1.0.0',
            'active' => true,
            'integrationId' => $ids->get('integration'),
            'aclRoleId' => $ids->get('appRole'),
        ]], $context);

        $integration = $this->findIntegration('frosh_tools_app_test');

        static::assertTrue($integration['managedByApp']);
        static::assertSame('FroshToolsAppTest', $integration['appName']);

        // Apps grant permissions via app.acl_role_id, which Shopware resolves with
        // precedence over integration_role - that role must not go missing here.
        static::assertContains($ids->get('appRole'), $integration['aclRoleIds']);
        static::assertContains($ids->get('otherRole'), $integration['aclRoleIds']);
    }

    public function testAclRolesAreHashedInsteadOfListed(): void
    {
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();

        $this->createAclRoles(['role' => 'frosh_tools_hash_test'], $context, $ids, ['order:read']);

        $roles = $this->accounts->getAclRoles();
        static::assertNotEmpty($roles, 'The fixture role must be returned');

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
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();

        $this->createAclRoles(['role' => 'frosh_tools_account_test'], $context, $ids, ['b:read', 'a:read']);

        $before = $this->findRole('frosh_tools_account_test');

        $aclRoles = $this->getRepository(AclRoleDefinition::ENTITY_NAME, AclRoleCollection::class);
        $aclRoles->update([[
            'id' => $ids->get('role'),
            'privileges' => ['a:read', 'b:read'],
        ]], $context);

        $reordered = $this->findRole('frosh_tools_account_test');
        static::assertSame($before['privilegesHash'], $reordered['privilegesHash'], 'Reordering privileges must not look like a change');

        $aclRoles->update([[
            'id' => $ids->get('role'),
            'privileges' => ['a:read', 'b:read', 'c:write'],
        ]], $context);

        $escalated = $this->findRole('frosh_tools_account_test');
        static::assertNotSame($before['privilegesHash'], $escalated['privilegesHash'], 'An added privilege must be detectable');
        static::assertSame(3, $escalated['privilegeCount']);
    }

    private function createIntegration(string $id, string $label, Context $context): void
    {
        $this->getRepository(IntegrationDefinition::ENTITY_NAME, IntegrationCollection::class)->create([[
            'id' => $id,
            'label' => $label,
            'accessKey' => $id,
            'secretAccessKey' => 'frosh_tools_test_secret',
        ]], $context);
    }

    /**
     * @param array<string, string> $names keyed by IdsCollection key
     * @param list<string> $privileges
     */
    private function createAclRoles(array $names, Context $context, IdsCollection $ids, array $privileges = ['app:read']): void
    {
        $payload = [];
        foreach ($names as $key => $name) {
            $payload[] = [
                'id' => $ids->get($key),
                'name' => $name,
                'privileges' => $privileges,
            ];
        }

        $this->getRepository(AclRoleDefinition::ENTITY_NAME, AclRoleCollection::class)->create($payload, $context);
    }

    /**
     * @param class-string<EntityCollection<Entity>> $collectionClass documents the repository's entity type
     *
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function getRepository(string $entity, string $collectionClass): EntityRepository
    {
        $repository = static::getContainer()->get($entity . '.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }

    /**
     * @return array<string, mixed>
     */
    private function findIntegration(string $label): array
    {
        foreach ($this->accounts->getIntegrations() as $integration) {
            if ($integration['label'] === $label) {
                return $integration;
            }
        }

        static::fail(\sprintf('Integration "%s" not found', $label));
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
