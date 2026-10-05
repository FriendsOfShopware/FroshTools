<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Security\Activity;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\Activity\ActivityStore;
use Frosh\Tools\Tests\IntegrationTestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

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
        $this->store->append('customer:password_reset', 200, ['entityId' => $id], $now);
        $this->store->append('user:login_failed', 300, ['actorType' => 'anonymous', 'loginUsername' => $id], $now);
        foreach (['users' => 3, 'integrations' => 3, 'keys' => 1] as $type => $count) {
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

    public function testActorOptionsAreBoundedAndSearchCanFindLaterActors(): void
    {
        for ($i = 0; $i < 55; ++$i) {
            $this->store->append('user:login', 200, ['loginUsername' => sprintf('actor%02d', $i)], new \DateTimeImmutable());
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

    public function testConfiguredLoggerCapturesExistingChannelExactlyOnce(): void
    {
        static::getContainer()->get('monolog.logger.system_activity')->info('plugin:install', ['pluginName' => 'Example', 'actorType' => 'system']);
        $result = $this->store->search(1, 25, 'plugin:install', '', null, null);
        static::assertSame(1, $result['total']);
        static::assertSame('Example', $result['entries'][0]['context']['pluginName']);
    }
}
