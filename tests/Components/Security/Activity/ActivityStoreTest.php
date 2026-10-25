<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Activity;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\Activity\ActivityStore;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
class ActivityStoreTest extends IntegrationTestCase
{
    private ActivityStore $store;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM frosh_tools_security_activity');
        $this->store = new ActivityStore($connection);
    }

    public function testExportTraversesEqualTimestampBatchesAndAppliesTheSameFilters(): void
    {
        $date = new \DateTimeImmutable('2026-10-04 12:00:00 UTC');
        $userId = Uuid::randomHex();
        for ($i = 0; $i < 503; ++$i) {
            $this->store->append('user:update', 200, ['actorType' => 'user', 'userId' => $userId, 'username' => 'alice', 'clientIp' => '2001:db8::1'], $date);
        }
        $this->store->append('user:login', 200, ['actorType' => 'user', 'userId' => $userId], $date);
        $this->store->append('user:update', 200, ['actorType' => 'user', 'userId' => Uuid::randomHex(), 'username' => 'alice'], $date);
        $from = new \DateTimeImmutable('2026-10-04 UTC');
        $entries = iterator_to_array($this->store->export('user:update', 'alice', $from, $from, true, '2001:0db8::1', $userId));
        static::assertCount(503, $entries);
        static::assertCount(503, array_unique(array_column($entries, 'id')));
        static::assertSame($this->store->search(1, 600, 'user:update', 'alice', $from, $from, true, '2001:db8::1', $userId)['entries'], $entries);
        static::assertSame([], iterator_to_array($this->store->export('missing', '', null, null)));
    }

    public function testSubjectIncludesActorAndTypedTargetsWithoutMixingEntityTypes(): void
    {
        $id = Uuid::randomHex();
        $now = new \DateTimeImmutable();
        $this->store->append('user:login', 200, ['actorType' => 'user', 'userId' => $id], $now);
        $this->store->append('user:update', 200, ['actorType' => 'system', 'entityId' => $id], $now);
        $this->store->append('user:password_reset', 200, ['actorType' => 'system', 'targetUserId' => $id], $now);
        $this->store->append('integration:update', 200, ['actorType' => 'system', 'entityId' => $id], $now);
        $this->store->append('plugin:install', 200, ['actorType' => 'integration', 'integrationId' => $id], $now);
        $this->store->append('integration_role:delete', 200, ['entityId' => $id], $now);
        $this->store->append('user_access_key:delete', 200, ['entityId' => $id], $now);
        $this->store->append('user_access_key:create', 200, ['entityId' => Uuid::randomHex(), 'targetUserId' => $id], $now);
        $this->store->append('user_access_key:update', 200, ['entityId' => Uuid::randomHex(), 'targetUserId' => $id], $now);
        $this->store->append('customer:password_reset', 200, ['entityId' => $id], $now);
        $this->store->append('user:login_failed', 300, ['actorType' => 'anonymous', 'loginUsername' => $id], $now);
        foreach (['users' => 5, 'integrations' => 3, 'keys' => 1] as $type => $count) {
            $result = $this->store->search(1, 25, '', '', null, null, subjectType: $type, subjectId: $id);
            static::assertSame($count, $result['total'], $type);
            static::assertSame($result['entries'], iterator_to_array($this->store->export('', '', null, null, subjectType: $type, subjectId: $id)));
        }
        static::assertSame(1, $this->store->search(1, 25, 'user:password_reset', '', null, null, subjectType: 'users', subjectId: $id)['total']);
        static::assertSame(0, $this->store->search(1, 25, '', '', null, null, subjectType: 'users', subjectId: Uuid::randomHex())['total']);
    }

    public function testFiltersEquivalentIpAddressesAndStableUserIdentity(): void
    {
        $userId = Uuid::randomHex();
        $this->store->append('user:update', 200, ['actorType' => 'user', 'userId' => $userId, 'username' => 'old-name', 'clientIp' => '2001:db8::1'], new \DateTimeImmutable());
        $this->store->append('user:update', 200, ['actorType' => 'user', 'userId' => $userId, 'username' => 'new-name', 'clientIp' => '192.0.2.1'], new \DateTimeImmutable());
        $this->store->append('user:update', 200, ['actorType' => 'user', 'userId' => Uuid::randomHex(), 'username' => 'old-name'], new \DateTimeImmutable());
        static::assertSame(1, $this->store->search(1, 25, '', '', null, null, clientIp: '2001:0db8:0:0:0:0:0:1')['total']);
        static::assertSame(1, $this->store->search(1, 25, '', '', null, null, clientIp: '192.0.2.1')['total']);
        static::assertSame(0, $this->store->search(1, 25, '', '', null, null, clientIp: '192.0.2.2')['total']);
        static::assertSame(2, $this->store->search(1, 25, '', '', null, null, userId: $userId)['total']);
        static::assertSame(1, $this->store->search(1, 25, '', '', null, null, clientIp: '192.0.2.1', userId: $userId)['total']);
    }

    public function testFiltersActorsActionsDatesAndPaginatesNewestFirst(): void
    {
        $this->store->append('user:update', 200, ['actorType' => 'user', 'userId' => 'actor', 'username' => 'alice'], new \DateTimeImmutable('2026-10-03 23:59:59 UTC'));
        $this->store->append('user:login', 200, ['actorType' => 'user', 'loginUsername' => 'alice'], new \DateTimeImmutable('2026-10-04 23:59:59 UTC'));
        $this->store->append('plugin:install', 200, ['actorType' => 'system'], new \DateTimeImmutable('2026-10-05 UTC'));
        $result = $this->store->search(1, 1, 'user:', 'ali', null, new \DateTimeImmutable('2026-10-04 UTC'));
        static::assertSame(2, $result['total']);
        static::assertSame('user:login', $result['entries'][0]['action']);
        static::assertSame('2026-10-04T23:59:59+00:00', $result['entries'][0]['createdAt']);
        $second = $this->store->search(2, 1, 'user:', 'ali', null, new \DateTimeImmutable('2026-10-04 UTC'));
        static::assertSame('user:update', $second['entries'][0]['action']);
        static::assertSame(1, $this->store->search(1, 25, '', 'actor', null, null)['total']);
        static::assertSame(1, $this->store->search(1, 25, '', 'system', new \DateTimeImmutable('2026-10-05 UTC'), null)['total']);
    }

    public function testSelectOptionsUseAllHistoryAndExactSelectionsDoNotMatchSimilarValues(): void
    {
        $now = new \DateTimeImmutable('2026-10-04 UTC');
        $this->store->append('user:login', 200, ['loginUsername' => 'alice'], $now);
        $this->store->append('user:login_failed', 300, ['loginUsername' => 'alice'], $now);
        $this->store->append('user:login', 200, ['loginUsername' => 'alice2'], $now);
        $this->store->append('plugin:install', 200, ['actorType' => 'system'], $now);
        static::assertSame(['values' => ['alice', 'alice2'], 'hasMore' => false], $this->store->filterOptions('actor', 'ali'));
        static::assertSame(['plugin:install', 'user:login', 'user:login_failed'], $this->store->filterOptions('action', '')['values']);
        $result = $this->store->search(1, 25, 'user:login', 'alice', null, null, exact: true);
        static::assertSame(1, $result['total']);
        static::assertSame('user:login', $result['entries'][0]['action']);
        static::assertSame('alice', $result['entries'][0]['context']['loginUsername']);
        static::assertSame(1, $this->store->search(1, 25, '', 'system', null, null, exact: true)['total']);
    }

    public function testActorLabelsPreferNamesThenIdsThenTypesIncludingEmptyValues(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->append('user:login', 200, ['actorType' => 'user', 'userId' => 'alice', 'username' => 'bob'], $now);
        $this->store->append('user:login', 200, ['actorType' => 'user', 'userId' => 'alice', 'username' => ''], $now);
        $this->store->append('user:login', 200, ['actorType' => 'user', 'userId' => '', 'username' => ''], $now);
        $this->store->append('plugin:install', 200, ['actorType' => 'system'], $now);
        $this->store->append('user:login_failed', 300, ['loginUsername' => ''], $now);

        static::assertSame(['alice', 'bob', 'system', 'unknown', 'user'], $this->store->filterOptions('actor', '')['values']);
        foreach (['alice', 'bob', 'system', 'unknown', 'user'] as $label) {
            $result = $this->store->search(1, 25, '', $label, null, null, exact: true);
            static::assertSame(1, $result['total'], $label);
            static::assertSame($result['entries'], iterator_to_array($this->store->export('', $label, null, null, exact: true)));
        }
        // Prefix searches still match an actor ID even when a name is displayed.
        static::assertSame(2, $this->store->search(1, 25, '', 'alice', null, null)['total']);
    }

    public function testActorOptionsAreBoundedAndSearchCanFindLaterActors(): void
    {
        for ($i = 0; $i < 55; ++$i) {
            $this->store->append('user:login', 200, ['loginUsername' => \sprintf('actor%02d', $i)], new \DateTimeImmutable());
        }
        $options = $this->store->filterOptions('actor', '');
        static::assertCount(50, $options['values']);
        static::assertTrue($options['hasMore']);
        static::assertSame(['values' => ['actor50', 'actor51', 'actor52', 'actor53', 'actor54'], 'hasMore' => false], $this->store->filterOptions('actor', '', 2));
        static::assertSame(['values' => ['actor54'], 'hasMore' => false], $this->store->filterOptions('actor', 'actor54'));
        static::assertSame([], $this->store->filterOptions('actor', 'actor%')['values']);
    }

    public function testWildcardsAreLiteralAndRetentionKeepsBoundary(): void
    {
        $this->store->append('user:login', 200, ['loginUsername' => 'a_%!'], new \DateTimeImmutable('2026-10-01 UTC'));
        $this->store->append('user:login', 200, ['loginUsername' => 'alice'], new \DateTimeImmutable('2026-10-02 UTC'));
        static::assertSame(1, $this->store->search(1, 25, '', 'a_%!', null, null)['total']);
        $this->store->deleteBefore(new \DateTimeImmutable('2026-10-02 UTC'));
        $remaining = $this->store->search(1, 25, '', '', null, null);
        static::assertSame(1, $remaining['total']);
        static::assertSame('alice', $remaining['entries'][0]['context']['loginUsername']);
    }

    public function testRoleChangesAreRecordedThroughDalEvents(): void
    {
        $roleId = Uuid::randomHex();
        $repository = static::getContainer()->get('acl_role.repository');
        $context = Context::createDefaultContext();
        $repository->create([['id' => $roleId, 'name' => 'Timeline role', 'privileges' => []]], $context);
        $repository->update([['id' => $roleId, 'privileges' => ['product:read']]], $context);
        $repository->delete([['id' => $roleId]], $context);
        $result = $this->store->search(1, 25, 'acl_role:', 'system', null, null);
        static::assertSame(3, $result['total']);
        static::assertEqualsCanonicalizing(['acl_role:create', 'acl_role:update', 'acl_role:delete'], array_column($result['entries'], 'action'));
        foreach ($result['entries'] as $entry) {
            static::assertSame($roleId, $entry['context']['entityId']);
            static::assertArrayNotHasKey('privileges', $entry['context']);
        }
    }

    public function testIntegrationNamesAreSnapshottedThroughConfiguredSubscribersAndProcessor(): void
    {
        $id = Uuid::randomHex();
        $repository = static::getContainer()->get('integration.repository');
        $context = Context::createDefaultContext();
        $repository->create([[
            'id' => $id,
            'label' => 'Original integration',
            'accessKey' => 'timeline-' . Uuid::randomHex(),
            'secretAccessKey' => 'test-secret',
        ]], $context);
        $repository->update([['id' => $id, 'label' => 'Renamed integration']], $context);
        $repository->update([['id' => $id, 'admin' => true]], $context);

        $created = $this->store->search(1, 25, 'integration:create', '', null, null, subjectType: 'integrations', subjectId: $id);
        static::assertSame(1, $created['total']);
        static::assertSame('Original integration', $created['entries'][0]['context']['integrationName']);
        $updated = $this->store->search(1, 25, 'integration:update', '', null, null, subjectType: 'integrations', subjectId: $id);
        static::assertSame(2, $updated['total']);
        foreach ($updated['entries'] as $entry) {
            static::assertSame('Renamed integration', $entry['context']['integrationName']);
        }
    }

    public function testConfiguredLoggerCapturesExistingChannelExactlyOnce(): void
    {
        static::getContainer()->get('monolog.logger.system_activity')->info('plugin:install', ['pluginName' => 'Example', 'actorType' => 'system']);
        $result = $this->store->search(1, 25, 'plugin:install', '', null, null);
        static::assertSame(1, $result['total']);
        static::assertSame('Example', $result['entries'][0]['context']['pluginName']);
    }

    #[DataProvider('entityClientAddresses')]
    public function testEntityActivityRetainsRequestIpInTimelineAndExport(string $ip): void
    {
        $requests = static::getContainer()->get(RequestStack::class);
        $requests->push(new Request(server: ['REMOTE_ADDR' => $ip]));
        try {
            static::getContainer()->get('acl_role.repository')->create([[
                'id' => Uuid::randomHex(),
                'name' => 'IP activity test',
                'privileges' => [],
            ]], Context::createDefaultContext());
        } finally {
            $requests->pop();
        }

        $result = $this->store->search(1, 25, 'acl_role:create', '', null, null, clientIp: $ip);
        static::assertSame(1, $result['total']);
        static::assertSame($ip, $result['entries'][0]['context']['clientIp']);
        static::assertSame($result['entries'], iterator_to_array($this->store->export('acl_role:create', '', null, null, clientIp: $ip)));

        // Background work must not inherit an address from an earlier request.
        static::getContainer()->get('acl_role.repository')->create([[
            'id' => Uuid::randomHex(),
            'name' => 'Background activity test',
            'privileges' => [],
        ]], Context::createDefaultContext());
        $all = $this->store->search(1, 25, 'acl_role:create', '', null, null);
        static::assertSame(2, $all['total']);
        static::assertCount(1, array_filter($all['entries'], static fn (array $entry): bool => isset($entry['context']['clientIp'])));
    }

    public static function entityClientAddresses(): iterable
    {
        yield 'IPv4' => ['192.0.2.10'];
        yield 'IPv6' => ['2001:db8::1'];
    }

    public function testConfiguredIntegrationActionsAppearInSubjectHistoryAndExport(): void
    {
        $integrationId = Uuid::randomHex();
        $context = new Context(new AdminApiSource(null, $integrationId));
        static::getContainer()->get('integration.repository')->create([[
            'id' => $integrationId,
            'label' => 'Timeline actor',
            'accessKey' => 'timeline-' . Uuid::randomHex(),
            'secretAccessKey' => 'test-secret',
        ]], Context::createDefaultContext());
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM frosh_tools_security_activity');
        $dispatcher = static::getContainer()->get('event_dispatcher');
        $userId = Uuid::randomHex();
        $dispatcher->dispatch(new EntityWrittenEvent('user', [
            new EntityWriteResult($userId, [], 'user', EntityWriteResult::OPERATION_INSERT),
        ], $context), 'user.written');
        $dispatcher->dispatch(new EntityWrittenEvent('user', [
            new EntityWriteResult($userId, ['active' => false], 'user', EntityWriteResult::OPERATION_UPDATE),
        ], $context), 'user.written');

        $result = $this->store->search(1, 25, '', '', null, null, subjectType: 'integrations', subjectId: $integrationId);
        static::assertSame(2, $result['total']);
        static::assertEqualsCanonicalizing(['user:create', 'user:update'], array_column($result['entries'], 'action'));
        foreach ($result['entries'] as $entry) {
            static::assertSame($integrationId, $entry['context']['integrationId']);
        }
        static::assertSame($result['entries'], iterator_to_array($this->store->export('', '', null, null, subjectType: 'integrations', subjectId: $integrationId)));
        static::assertSame(0, $this->store->search(1, 25, '', '', null, null, subjectType: 'integrations', subjectId: Uuid::randomHex())['total']);
    }

    public function testAccessKeyOnlyIntegrationActorsAreStoredWithTheirUuid(): void
    {
        $integrationId = Uuid::randomHex();
        $accessKey = 'timeline-' . Uuid::randomHex();
        static::getContainer()->get('integration.repository')->create([[
            'id' => $integrationId,
            'label' => 'Timeline integration',
            'accessKey' => $accessKey,
            'secretAccessKey' => 'test-secret',
        ]], Context::createDefaultContext());
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM frosh_tools_security_activity');

        static::getContainer()->get('monolog.logger.system_activity')->info('plugin:install', [
            'actorType' => 'integration',
            'integrationAccessKey' => $accessKey,
            'pluginName' => 'Example',
        ]);

        $result = $this->store->search(1, 25, '', '', null, null, subjectType: 'integrations', subjectId: $integrationId);
        static::assertSame(1, $result['total']);
        static::assertSame($integrationId, $result['entries'][0]['context']['integrationId']);
        static::assertSame($result['entries'], iterator_to_array($this->store->export('', '', null, null, subjectType: 'integrations', subjectId: $integrationId)));
        static::assertSame(1, $this->store->search(1, 25, '', $integrationId, null, null, exact: true)['total']);
    }
}
