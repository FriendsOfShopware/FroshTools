<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Components\Telemetry;

use Frosh\Tools\Components\Telemetry\TelemetryCollector;
use Frosh\Tools\Tests\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(TelemetryCollector::class)]
class TelemetryCollectorTest extends IntegrationTestCase
{
    public function testCollectMatchesThePostHogEventSchema(): void
    {
        $collector = static::getContainer()->get(TelemetryCollector::class);
        $properties = $collector->collect();

        // Types as defined in the PostHog event schema; every property is optional
        // there, but on a healthy test installation all of them must be present.
        $booleans = ['cache.apcu_enabled', 'cache.opcache_enabled', 'cache.redis_enabled', 'php.apcu_enabled', 'php.opcache_enabled', 'search.enabled', 'app.prod', 'app.debug', 'admin_worker.enabled', 'shopware.runtime_extension_management', 'frosh_tools.managed_by_composer', 'http_cache.reverse_proxy_enabled', 'mail.queue_enabled', 'php.redis_enabled', 'php.amqp_enabled', 'php.intl_enabled', 'php.imagick_enabled', 'php.gd_enabled', 'php.xdebug_enabled', 'php.ioncube_enabled', 'php.tideways_enabled', 'php.blackfire_enabled', 'php.newrelic_enabled'];
        foreach ($booleans as $key) {
            static::assertArrayHasKey($key, $properties);
            static::assertIsBool($properties[$key], $key);
        }

        $strings = ['database.type', 'database.version_major_minor', 'php.version_major_minor', 'queue.transports', 'schema_version', 'search.engine', 'shop.customers', 'shop.orders', 'shop.products', 'shopware.version', 'shopware.version_major_minor', 'symfony.version_major_minor', 'frosh_tools.version', 'frosh_tools.version_major_minor', 'http_cache.type', 'session.handler', 'increment.user_activity', 'increment.message_queue', 'storage.media_type', 'mail.transport'];
        foreach ($strings as $key) {
            static::assertArrayHasKey($key, $properties);
            static::assertIsString($properties[$key], $key);
        }

        $numerics = ['shop.apps_active', 'shop.apps_total', 'shop.extensions_active', 'shop.extensions_total'];
        foreach ($numerics as $key) {
            static::assertArrayHasKey($key, $properties);
            static::assertIsInt($properties[$key], $key);
            static::assertGreaterThanOrEqual(0, $properties[$key], $key);
        }

        static::assertSame(TelemetryCollector::SCHEMA_VERSION, $properties['schema_version']);
        static::assertContains($properties['database.type'], ['mysql', 'mariadb']);
        static::assertMatchesRegularExpression('/^\d+\.\d+$/', $properties['database.version_major_minor']);
        static::assertMatchesRegularExpression('/^\d+\.\d+$/', $properties['php.version_major_minor']);
        static::assertMatchesRegularExpression('/^\d+\.\d+$/', $properties['shopware.version_major_minor']);
        static::assertNotFalse(\DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, (string) $properties['collected_at']));

        foreach (['shop.products', 'shop.orders', 'shop.customers'] as $key) {
            static::assertMatchesRegularExpression('/^(0|1-10|11-100|101-1000|1001-10000|10001-100000|100001\+)$/', $properties[$key], $key);
        }

        static::assertLessThanOrEqual($properties['shop.extensions_total'], $properties['shop.extensions_active']);
        static::assertLessThanOrEqual($properties['shop.apps_total'], $properties['shop.apps_active']);
        static::assertSame($properties['search.enabled'], \in_array($properties['search.engine'], ['elasticsearch', 'opensearch'], true));

        // Environment properties (schema v2): fixed value sets only, nothing shop-specific.
        static::assertFalse($properties['app.prod']); // integration tests run in the "test" environment
        static::assertMatchesRegularExpression('/^\d+\.\d+$/', $properties['symfony.version_major_minor']);
        static::assertMatchesRegularExpression('/^\d+\.\d+$/', $properties['frosh_tools.version_major_minor']);
        static::assertStringStartsWith($properties['frosh_tools.version_major_minor'], $properties['frosh_tools.version']);
        static::assertContains($properties['http_cache.type'], ['builtin', 'varnish', 'fastly']);
        static::assertSame($properties['http_cache.reverse_proxy_enabled'], $properties['http_cache.type'] !== 'builtin');
        static::assertContains($properties['session.handler'], ['file', 'redis', 'memcached', 'database', 'mongodb', 'null', 'other', 'unknown']);
        static::assertContains($properties['increment.user_activity'], ['mysql', 'redis', 'array', 'other']);
        static::assertContains($properties['increment.message_queue'], ['mysql', 'redis', 'array', 'other']);
        static::assertContains($properties['storage.media_type'], ['local', 'amazon-s3', 'google-storage', 'other']);
        static::assertMatchesRegularExpression('/^[a-z]+$/', $properties['mail.transport']);
        static::assertSame(\extension_loaded('intl'), $properties['php.intl_enabled']);
        static::assertSame(\extension_loaded('gd'), $properties['php.gd_enabled']);

        // The mailer configured in the admin wins over MAILER_DSN (like Shopware's MailerTransportLoader).
        static::getContainer()->get(SystemConfigService::class)->set('core.mailerSettings.emailAgent', 'local');
        static::assertSame('sendmail', $collector->collect()['mail.transport']);

        // Nothing identifying may ever end up in the payload.
        foreach (array_keys($properties) as $key) {
            static::assertStringNotContainsString('url', $key);
            static::assertStringNotContainsString('host', $key);
            static::assertStringNotContainsString('name', $key);
            static::assertStringNotContainsString('email', $key);
        }
    }
}
