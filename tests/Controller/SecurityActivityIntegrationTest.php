<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Acl\FroshToolsPrivileges;
use Frosh\Tools\Components\Security\Activity\ActivityStore;
use Frosh\Tools\Tests\IntegrationTestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class SecurityActivityIntegrationTest extends IntegrationTestCase
{
    use AdminApiTestBehaviour;

    public function testSecurityViewerCanReadTimelineWithoutGenericLogAccess(): void
    {
        $browser = $this->createClient(authorized: false);
        $browser->catchExceptions(false);
        $this->authorizeBrowser($browser, aclPermissions: [FroshToolsPrivileges::SECURITY_READ]);
        $login = static::getContainer()->get(ActivityStore::class)->search(1, 25, 'user:login', '', null, null);
        static::assertGreaterThanOrEqual(1, $login['total']);
        static::assertTrue(\Shopware\Core\Framework\Uuid\Uuid::isValid($login['entries'][0]['context']['userId']));
        $browser->request('GET', '/api/_action/frosh-tools/security/activity');
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $body = json_decode((string) $browser->getResponse()->getContent(), true);
        static::assertArrayHasKey('entries', $body);
        $browser->request('GET', '/api/_action/frosh-tools/security/activity/options?field=actor');
        static::assertSame(200, $browser->getResponse()->getStatusCode());
    }

    public function testViewerCanDownloadFilteredJsonBeyondTheVisiblePage(): void
    {
        $browser = $this->createClient(authorized: false);
        $this->authorizeBrowser($browser, aclPermissions: [FroshToolsPrivileges::SECURITY_READ]);
        $store = static::getContainer()->get(ActivityStore::class);
        for ($i = 0; $i < 30; ++$i) {
            $store->append('export:test', 200, ['actorType' => 'system'], new \DateTimeImmutable());
        }
        $browser->request('GET', '/api/_action/frosh-tools/security/activity/export?action=export:test&exact=1&page=2&limit=1');
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        static::assertTrue($browser->getResponse()->headers->hasCacheControlDirective('no-store'));
        static::assertSame('attachment; filename="security-activity.json"', $browser->getResponse()->headers->get('Content-Disposition'));
        $body = json_decode($browser->getInternalResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertCount(30, $body['entries']);
        static::assertArrayHasKey('exportedAt', $body);
        static::assertSame(['export:test'], array_values(array_unique(array_column($body['entries'], 'action'))));
        $browser->request('GET', '/api/_action/frosh-tools/security/activity/export?clientIp=invalid');
        static::assertSame(400, $browser->getResponse()->getStatusCode());
    }

    public function testSubjectScopeHasListAndExportParityAndRejectsIncompleteFilters(): void
    {
        $browser = $this->createClient(authorized: false);
        $this->authorizeBrowser($browser, aclPermissions: [FroshToolsPrivileges::SECURITY_READ]);
        $store = static::getContainer()->get(ActivityStore::class);
        $id = \Shopware\Core\Framework\Uuid\Uuid::randomHex();
        $store->append('user:update', 200, ['actorType' => 'system', 'entityId' => $id], new \DateTimeImmutable());
        $store->append('incident:user_contained', 200, ['actorType' => 'system', 'targetUserId' => $id], new \DateTimeImmutable());
        $store->append('customer:update', 200, ['entityId' => $id], new \DateTimeImmutable());
        $browser->request('GET', '/api/_action/frosh-tools/security/activity?subjectType=users&subjectId=' . strtoupper($id));
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $list = json_decode((string) $browser->getResponse()->getContent(), true);
        static::assertSame(2, $list['total']);
        $browser->request('GET', '/api/_action/frosh-tools/security/activity/export?subjectType=users&subjectId=' . $id);
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        $export = json_decode($browser->getInternalResponse()->getContent(), true);
        static::assertSame($list['entries'], $export['entries']);
        foreach (['activity', 'activity/export'] as $route) {
            $browser->request('GET', '/api/_action/frosh-tools/security/' . $route . '?subjectType=users');
            static::assertSame(400, $browser->getResponse()->getStatusCode());
        }
    }

    public function testConfiguredLoggerRedactsSecretsBeforeListAndExport(): void
    {
        $browser = $this->createClient(authorized: false);
        $this->authorizeBrowser($browser, aclPermissions: [FroshToolsPrivileges::SECURITY_READ]);
        $actor = 'redaction-' . \Shopware\Core\Framework\Uuid\Uuid::randomHex();
        static::getContainer()->get('monolog.logger.system_activity')->info('user:update', [
            'actorType' => 'user', 'username' => $actor, 'changedFields' => ['password', 'email'],
            'password' => 'private-timeline-password', 'accessToken' => 'private-timeline-bearer',
            'refreshToken' => 'private-timeline-refresh', 'recoveryHash' => 'private-timeline-recovery',
            'headers' => ['Authorization' => 'private-timeline-header'],
        ]);
        foreach (['activity', 'activity/export'] as $route) {
            $browser->request('GET', '/api/_action/frosh-tools/security/' . $route . '?actor=' . $actor . '&exact=1');
            static::assertSame(200, $browser->getResponse()->getStatusCode());
            $content = $browser->getInternalResponse()->getContent();
            static::assertStringNotContainsString('private-timeline-', $content);
            $entries = json_decode($content, true, flags: \JSON_THROW_ON_ERROR)['entries'];
            static::assertCount(1, $entries);
            static::assertEqualsCanonicalizing(['password', 'email'], $entries[0]['context']['changedFields']);
            static::assertSame($actor, $entries[0]['context']['username']);
        }
    }

    public function testConfiguredCleanupRemovesExpiredHistoryFromListOptionsAndExport(): void
    {
        $browser = $this->createClient(authorized: false);
        $this->authorizeBrowser($browser, aclPermissions: [FroshToolsPrivileges::SECURITY_READ]);
        $container = static::getContainer();
        $store = $container->get(ActivityStore::class);
        $days = $container->getParameter('frosh_tools.security_activity.retention_days');
        $actor = 'retention-' . \Shopware\Core\Framework\Uuid\Uuid::randomHex();
        $oldActor = $actor . '-expired';
        $old = $store->append('user:login', 200, ['username' => $oldActor], new \DateTimeImmutable('-' . ($days + 1) . ' days'));
        $retained = $store->append('user:login', 200, ['username' => $actor], new \DateTimeImmutable());
        $container->get(\Frosh\Tools\Components\Security\Activity\ActivityCleanupTaskHandler::class)->run();
        foreach (['activity', 'activity/export'] as $route) {
            $browser->request('GET', '/api/_action/frosh-tools/security/' . $route . '?actor=' . $actor);
            static::assertSame(200, $browser->getResponse()->getStatusCode());
            $body = json_decode($browser->getInternalResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
            static::assertSame([$retained], array_column($body['entries'], 'id'));
            static::assertStringNotContainsString($old, $browser->getInternalResponse()->getContent());
        }
        $browser->request('GET', '/api/_action/frosh-tools/security/activity/options?field=actor&term=' . $actor);
        static::assertSame(200, $browser->getResponse()->getStatusCode());
        static::assertSame([$actor], json_decode($browser->getInternalResponse()->getContent(), true)['values']);
    }

    public function testOtherViewersCannotReadTimeline(): void
    {
        $browser = $this->createClient(authorized: false);
        $browser->catchExceptions(false);
        $this->authorizeBrowser($browser, aclPermissions: [FroshToolsPrivileges::READ, FroshToolsPrivileges::LOGS_READ]);
        $browser->catchExceptions(true);
        $browser->request('GET', '/api/_action/frosh-tools/security/activity');
        static::assertSame(403, $browser->getResponse()->getStatusCode());
        $browser->request('GET', '/api/_action/frosh-tools/security/activity/export');
        static::assertSame(403, $browser->getResponse()->getStatusCode());
        $browser->request('GET', '/api/_action/frosh-tools/security/activity/options?field=actor');
        static::assertSame(403, $browser->getResponse()->getStatusCode());
    }

    public function testFailedLoginIsRecordedThroughTheRealEndpoint(): void
    {
        $browser = $this->createClient(authorized: false);
        $browser->jsonRequest('POST', '/api/oauth/token', ['client_id' => 'administration', 'grant_type' => 'password', 'username' => 'timeline-missing-user', 'password' => 'not-a-password']);
        static::assertGreaterThanOrEqual(400, $browser->getResponse()->getStatusCode());
        $result = static::getContainer()->get(ActivityStore::class)->search(1, 25, 'user:login_failed', 'timeline-missing-user', null, null);
        static::assertSame(1, $result['total']);
        static::assertSame('anonymous', $result['entries'][0]['context']['actorType']);
    }
}
