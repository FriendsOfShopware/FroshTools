<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Frosh\Tools\Components\Security\Activity\ActivityHandler;
use Frosh\Tools\Components\Security\Activity\ActivityStore;
use Monolog\Level;
use Monolog\LogRecord;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

// Run from a disposable Shopware root using the browser server's DATABASE_URL.
require getcwd() . '/vendor/autoload.php';

$userId = $argv[1] ?? '';
$mode = $argv[2] ?? '';
if (!Uuid::isValid($userId) || !in_array($mode, ['seed', 'cleanup'], true)) {
    throw new RuntimeException('A browser fixture UUID and seed/cleanup mode are required.');
}
$connection = DriverManager::getConnection((new DsnParser(['mysql' => 'pdo_mysql']))->parse((string) getenv('DATABASE_URL')));
$connection->transactional(static function () use ($connection, $userId, $mode): void {
    $username = $connection->fetchOne('SELECT username FROM `user` WHERE id = UNHEX(?) FOR UPDATE', [$userId]);
    if ($username !== 'frosh-timeline-' . $userId) {
        throw new RuntimeException('Only the matching temporary browser fixture may be changed.');
    }
    $connection->delete('frosh_tools_security_activity', ['actor_id' => $userId]);
    if ($mode === 'cleanup') {
        return;
    }
    $store = new ActivityStore($connection);
    $requests = new RequestStack();
    $handler = new ActivityHandler($store, new NullLogger(), $requests);
    $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
    for ($i = 0; $i < 34; ++$i) {
        $requests->push(new Request(server: ['REMOTE_ADDR' => $i === 32 ? '192.0.2.27' : '2001:db8::27']));
        $handler->handle(new LogRecord(
            datetime: $i === 31 ? $today->modify('-1 day') : $today->modify('+' . $i . ' seconds'),
            channel: 'system_activity',
            level: Level::Info,
            message: $i === 30 ? 'user:login_failed' : 'user:login',
            context: [
                'actorType' => 'user', 'userId' => $userId,
                'username' => $username . ($i === 33 ? '-other' : ''),
                'password' => 'timeline-private-credential', 'accessToken' => 'timeline-private-credential',
                'headers' => ['Authorization' => 'timeline-private-credential'],
            ],
        ));
        $requests->pop();
    }
    if ((int) $connection->fetchOne('SELECT COUNT(*) FROM frosh_tools_security_activity WHERE actor_id = ?', [$userId]) !== 34) {
        throw new RuntimeException('Timeline fixture was not persisted completely.');
    }
});
