<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\Security\Activity\ActivityStore;
use Frosh\Tools\Tests\IntegrationTestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class RecoveryActivityIntegrationTest extends IntegrationTestCase
{
    use AdminApiTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testCustomerRecoveryRequestAndResetAreRecordedWithoutSecrets(): void
    {
        $browser = $this->createSalesChannelBrowser();
        $customerId = $this->login($browser);
        $connection = static::getContainer()->get(Connection::class);
        $email = $connection->fetchOne('SELECT email FROM customer WHERE id = :id', ['id' => Uuid::fromHexToBytes($customerId)]);
        $browser->request('POST', '/store-api/account/recovery-password', ['email' => $email, 'storefrontUrl' => 'http://localhost']);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $hash = $connection->fetchOne('SELECT hash FROM customer_recovery WHERE customer_id = :id', ['id' => Uuid::fromHexToBytes($customerId)]);
        static::assertIsString($hash);
        $browser->request('POST', '/store-api/account/recovery-password-confirm', ['hash' => $hash, 'newPassword' => 'new-private-password123', 'newPasswordConfirm' => 'new-private-password123']);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $store = static::getContainer()->get(ActivityStore::class);
        $requested = $store->search(1, 25, 'customer:recovery_requested', '', null, null);
        $completed = $store->search(1, 25, 'customer:password_reset', '', null, null);
        static::assertSame(1, $requested['total']);
        static::assertSame(1, $completed['total']);
        static::assertSame($customerId, $completed['entries'][0]['context']['entityId']);
        static::assertSame('anonymous', $requested['entries'][0]['context']['actorType']);
        static::assertSame('recovery', $completed['entries'][0]['context']['actorType']);
        $encoded = json_encode([$requested, $completed], \JSON_THROW_ON_ERROR);
        static::assertStringNotContainsString($hash, $encoded);
        static::assertStringNotContainsString('new-private-password123', $encoded);
        static::assertStringNotContainsString($email, $encoded);
        $browser->request('POST', '/store-api/account/recovery-password-confirm', ['hash' => $hash, 'newPassword' => 'new-private-password123', 'newPasswordConfirm' => 'new-private-password123']);
        static::assertGreaterThanOrEqual(400, $browser->getResponse()->getStatusCode());
        static::assertSame(1, $store->search(1, 25, 'customer:password_reset', '', null, null)['total']);
    }

    public function testAdminRecoveryRequestAndResetAreRecordedOnce(): void
    {
        $this->createSalesChannelBrowser();
        $id = Uuid::randomHex();
        $email = $id . '@example.com';
        static::getContainer()->get('user.repository')->create([[
            'id' => $id, 'username' => $id, 'firstName' => 'Recovery', 'lastName' => 'Test',
            'email' => $email, 'password' => 'old-private-password123',
            'localeId' => $this->getLocaleIdOfSystemLanguage(), 'active' => true, 'admin' => false,
        ]], Context::createDefaultContext());
        $browser = $this->createClient(authorized: false);
        $browser->jsonRequest('POST', '/api/_action/user/user-recovery', ['email' => $email]);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $hash = static::getContainer()->get(Connection::class)->fetchOne('SELECT hash FROM user_recovery WHERE user_id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertIsString($hash);
        $browser->jsonRequest('PATCH', '/api/_action/user/user-recovery/password', ['hash' => $hash, 'password' => 'new-private-password123', 'passwordConfirm' => 'new-private-password123']);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        $store = static::getContainer()->get(ActivityStore::class);
        $requested = $store->search(1, 25, 'user:recovery_requested', '', null, null);
        $completed = $store->search(1, 25, 'user:password_reset', '', null, null);
        static::assertSame(1, $requested['total']);
        static::assertSame(1, $completed['total']);
        static::assertSame($id, $requested['entries'][0]['context']['entityId']);
        static::assertSame($id, $completed['entries'][0]['context']['entityId']);
        $encoded = json_encode([$requested, $completed], \JSON_THROW_ON_ERROR);
        static::assertStringNotContainsString($hash, $encoded);
        static::assertStringNotContainsString('new-private-password123', $encoded);
        static::assertStringNotContainsString($email, $encoded);
    }
}
