<?php

declare(strict_types=1);

namespace Frosh\Tools\Components\Telemetry;

use Doctrine\DBAL\Connection;
use Frosh\Tools\Components\CacheAdapter;
use Frosh\Tools\Components\CacheRegistry;
use Frosh\Tools\Components\Elasticsearch\ElasticsearchManager;
use Frosh\Tools\Components\Queue\QueueRegistry;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Collects the flat, anonymous property set sent as PostHog event properties.
 *
 * Every property is optional: a section that fails (e.g. an unreachable cache
 * backend) is omitted instead of failing the whole collection. Shop sizes are
 * bucketed into ranges, versions are reduced to major.minor where the schema
 * asks for it, and nothing identifying (URL, host, names, concrete records)
 * is ever collected.
 */
class TelemetryCollector
{
    public const SCHEMA_VERSION = '2';

    private const MAIL_TRANSPORTS = ['smtp', 'smtps', 'sendmail', 'null', 'native', 'failover', 'roundrobin', 'ses', 'sendgrid', 'mailgun', 'postmark', 'mailjet', 'azure', 'gmail', 'ohmysmtp', 'mailchimp', 'mandrill', 'mailpace', 'resend', 'scaleway', 'brevo', 'mailtrap'];
    private const STORAGE_TYPES = ['local', 'amazon-s3', 'google-storage'];
    private const INCREMENT_TYPES = ['mysql', 'redis', 'array'];

    /**
     * @param EntityRepository<PluginCollection> $pluginRepository
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly CacheRegistry $cacheRegistry,
        private readonly QueueRegistry $queueRegistry,
        private readonly EntityRepository $pluginRepository,
        private readonly EntityRepository $appRepository,
        private readonly ElasticsearchManager $elasticsearchManager,
        private readonly SystemConfigService $configService,
        #[Autowire(param: 'kernel.shopware_version')]
        private readonly string $shopwareVersion,
        #[Autowire(param: 'frosh_tools.elasticsearch.enabled')]
        private readonly bool $searchEnabled,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $kernelEnvironment,
        #[Autowire(param: 'kernel.debug')]
        private readonly bool $kernelDebug,
        #[Autowire(param: 'shopware.admin_worker.enable_admin_worker')]
        private readonly bool $adminWorkerEnabled,
        #[Autowire(param: 'shopware.increment.user_activity.type')]
        private readonly string $incrementUserActivityType,
        #[Autowire(param: 'shopware.increment.message_queue.type')]
        private readonly string $incrementMessageQueueType,
        #[Autowire(param: 'frosh_tools.mail_over_queue')]
        private readonly bool $mailOverQueue,
        #[Autowire(param: 'shopware.filesystem.public.type')]
        private readonly string $mediaStorageType,
        #[Autowire(param: 'shopware.http_cache.reverse_proxy.enabled')]
        private readonly bool $reverseProxyEnabled = false,
        #[Autowire(param: 'shopware.http_cache.reverse_proxy.fastly.enabled')]
        private readonly bool $fastlyEnabled = false,
        #[Autowire(param: 'shopware.deployment.runtime_extension_management')]
        private readonly bool $runtimeExtensionManagement = true,
        #[Autowire(service: 'session.handler')]
        private readonly ?\SessionHandlerInterface $sessionHandler = null,
    ) {
    }

    /**
     * @return array<string, bool|int|string>
     */
    public function collect(): array
    {
        $properties = [
            'schema_version' => self::SCHEMA_VERSION,
            'collected_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
            'shopware.version' => $this->shopwareVersion,
        ];

        $shopwareMajorMinor = self::majorMinor($this->shopwareVersion);
        if ($shopwareMajorMinor !== null) {
            $properties['shopware.version_major_minor'] = $shopwareMajorMinor;
        }

        $properties['php.version_major_minor'] = \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION;
        $properties['php.apcu_enabled'] = self::isApcuEnabled();
        $properties['php.opcache_enabled'] = self::isOpcacheEnabled();
        $properties['search.enabled'] = $this->searchEnabled;
        $properties['search.engine'] = $this->searchEnabled ? 'elasticsearch' : 'database';

        $this->collectEnvironment($properties);
        $this->collectCache($properties);
        $this->collectDatabase($properties);
        $this->collectQueue($properties);
        $this->collectExtensions($properties);
        $this->collectShopSizes($properties);
        $this->collectSearch($properties);

        ksort($properties);

        return $properties;
    }

    public static function bucket(int $count): string
    {
        return match (true) {
            $count <= 0 => '0',
            $count <= 10 => '1-10',
            $count <= 100 => '11-100',
            $count <= 1000 => '101-1000',
            $count <= 10000 => '1001-10000',
            $count <= 100000 => '10001-100000',
            default => '100001+',
        };
    }

    public static function majorMinor(string $version): ?string
    {
        if (preg_match('/(?:5\.5\.5-)?(?:mariadb-)?(\d+)\.(\d+)/i', $version, $matches) !== 1) {
            return null;
        }

        return $matches[1] . '.' . $matches[2];
    }

    /**
     * Maps a MAILER_DSN to its transport scheme only. Everything after the
     * scheme (hosts, credentials) is discarded, unknown schemes fail closed.
     */
    public static function mailTransport(?string $dsn): string
    {
        if ($dsn === null || preg_match('/^([a-z0-9+.-]+)/i', trim($dsn), $matches) !== 1) {
            return 'unknown';
        }

        $scheme = mb_strtolower($matches[1]);

        return \in_array($scheme, self::MAIL_TRANSPORTS, true) ? $scheme : 'other';
    }

    public static function httpCacheType(bool $reverseProxyEnabled, bool $fastlyEnabled): string
    {
        if ($fastlyEnabled) {
            return 'fastly';
        }

        return $reverseProxyEnabled ? 'varnish' : 'builtin';
    }

    public static function sessionHandlerType(?\SessionHandlerInterface $handler): string
    {
        $handler = self::unwrapSessionHandler($handler);
        if ($handler === null) {
            return 'unknown';
        }

        $parts = explode('\\', $handler::class);
        $class = mb_strtolower(end($parts));

        return match (true) {
            str_contains($class, 'redis') => 'redis',
            str_contains($class, 'memcached') => 'memcached',
            str_contains($class, 'pdo') => 'database',
            str_contains($class, 'mongo') => 'mongodb',
            str_contains($class, 'nativefile'), $class === 'sessionhandler' => 'file',
            str_contains($class, 'null') => 'null',
            default => 'other',
        };
    }

    /**
     * @param array<string, bool|int|string> $properties
     */
    private function collectEnvironment(array &$properties): void
    {
        // Only the prod/debug booleans, never the (possibly custom) environment name.
        $properties['app.prod'] = $this->kernelEnvironment === 'prod';
        $properties['app.debug'] = $this->kernelDebug;
        $properties['admin_worker.enabled'] = $this->adminWorkerEnabled;
        $properties['shopware.runtime_extension_management'] = $this->runtimeExtensionManagement;

        $symfonyMajorMinor = self::majorMinor(Kernel::VERSION);
        if ($symfonyMajorMinor !== null) {
            $properties['symfony.version_major_minor'] = $symfonyMajorMinor;
        }

        $properties['php.redis_enabled'] = \extension_loaded('redis');
        $properties['php.amqp_enabled'] = \extension_loaded('amqp');
        $properties['php.intl_enabled'] = \extension_loaded('intl');
        $properties['php.imagick_enabled'] = \extension_loaded('imagick');
        $properties['php.gd_enabled'] = \extension_loaded('gd');
        $properties['php.xdebug_enabled'] = self::isXdebugEnabled();
        $properties['php.ioncube_enabled'] = \extension_loaded('ionCube Loader');
        $properties['php.tideways_enabled'] = \extension_loaded('tideways');
        $properties['php.blackfire_enabled'] = \extension_loaded('blackfire');
        $properties['php.newrelic_enabled'] = \extension_loaded('newrelic');

        $properties['http_cache.reverse_proxy_enabled'] = $this->reverseProxyEnabled || $this->fastlyEnabled;
        $properties['http_cache.type'] = self::httpCacheType($this->reverseProxyEnabled, $this->fastlyEnabled);
        $properties['session.handler'] = self::sessionHandlerType($this->sessionHandler);
        $properties['increment.user_activity'] = self::allowlisted($this->incrementUserActivityType, self::INCREMENT_TYPES);
        $properties['increment.message_queue'] = self::allowlisted($this->incrementMessageQueueType, self::INCREMENT_TYPES);
        $properties['storage.media_type'] = self::allowlisted($this->mediaStorageType, self::STORAGE_TYPES);
        $properties['mail.transport'] = $this->detectMailTransport();
        $properties['mail.queue_enabled'] = $this->mailOverQueue;

        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('name', 'FroshTools'));
            $criteria->setLimit(1);
            $plugin = $this->pluginRepository->search($criteria, Context::createCLIContext())->getEntities()->first();

            if ($plugin !== null) {
                $properties['frosh_tools.version'] = $plugin->getVersion();
                $froshMajorMinor = self::majorMinor($plugin->getVersion());
                if ($froshMajorMinor !== null) {
                    $properties['frosh_tools.version_major_minor'] = $froshMajorMinor;
                }
                $properties['frosh_tools.managed_by_composer'] = $plugin->getManagedByComposer();
            }
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string, bool|int|string> $properties
     */
    private function collectSearch(array &$properties): void
    {
        if (!$this->searchEnabled) {
            return;
        }

        try {
            $info = $this->elasticsearchManager->info()['info'] ?? null;
            if (\is_array($info)) {
                $version = \is_array($info['version'] ?? null) ? $info['version'] : [];
                $distribution = mb_strtolower((string) ($version['distribution'] ?? ''));
                $properties['search.engine'] = $distribution === 'opensearch' ? 'opensearch' : 'elasticsearch';

                $versionMajorMinor = self::majorMinor((string) ($version['number'] ?? ''));
                if ($versionMajorMinor !== null) {
                    $properties['search.version_major_minor'] = $versionMajorMinor;
                }
            }

            $properties['search.indices'] = self::bucket(\count($this->elasticsearchManager->indices()));
        } catch (\Throwable) {
        }
    }

    /**
     * Same precedence as Shopware's MailerTransportLoader: the mailer configured
     * in the admin (core.mailerSettings.emailAgent) wins over MAILER_DSN.
     */
    private function detectMailTransport(): string
    {
        try {
            $emailAgent = trim($this->configService->getString('core.mailerSettings.emailAgent'));
            if ($emailAgent !== '') {
                return match ($emailAgent) {
                    'smtp', 'smtp+oauth' => 'smtp',
                    'local' => 'sendmail',
                    default => 'other',
                };
            }
        } catch (\Throwable) {
        }

        $envDsn = getenv('MAILER_DSN');
        $mailerDsn = EnvironmentHelper::getVariable('MAILER_DSN', $envDsn !== false && $envDsn !== '' ? $envDsn : null);

        return self::mailTransport(\is_string($mailerDsn) ? $mailerDsn : null);
    }

    private static function unwrapSessionHandler(?\SessionHandlerInterface $handler): ?\SessionHandlerInterface
    {
        // StrictSessionHandler and MigratingSessionHandler wrap the real handler.
        while ($handler !== null) {
            $inner = null;
            foreach (['handler', 'currentHandler'] as $property) {
                if (!property_exists($handler, $property)) {
                    continue;
                }

                $getter = \Closure::bind(fn () => $handler->{$property}, $handler, $handler::class);
                $value = $getter();
                if ($value instanceof \SessionHandlerInterface) {
                    $inner = $value;
                }
                break;
            }

            if ($inner === null || $inner === $handler) {
                break;
            }

            $handler = $inner;
        }

        return $handler;
    }

    /**
     * @param list<string> $allowed
     */
    private static function allowlisted(string $value, array $allowed): string
    {
        $value = mb_strtolower(trim($value));

        return \in_array($value, $allowed, true) ? $value : 'other';
    }

    private static function isXdebugEnabled(): bool
    {
        if (!\extension_loaded('xdebug')) {
            return false;
        }

        $mode = \ini_get('xdebug.mode');

        return !\is_string($mode) || trim($mode) === '' || !str_contains($mode, 'off');
    }

    /**
     * @param array<string, bool|int|string> $properties
     */
    private function collectCache(array &$properties): void
    {
        try {
            $apcu = false;
            $opcache = false;
            $redis = false;

            foreach ($this->cacheRegistry->all() as $adapter) {
                try {
                    $type = $adapter->getType();
                } catch (\Throwable) {
                    continue;
                }

                $apcu = $apcu || $type === CacheAdapter::TYPE_APCU;
                // The PhpFilesAdapter pool is stored in OPcache.
                $opcache = $opcache || $type === CacheAdapter::TYPE_PHP_FILES;
                $redis = $redis || str_starts_with($type, CacheAdapter::TYPE_REDIS);
            }

            $properties['cache.apcu_enabled'] = $apcu;
            $properties['cache.opcache_enabled'] = $opcache;
            $properties['cache.redis_enabled'] = $redis;
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string, bool|int|string> $properties
     */
    private function collectDatabase(array &$properties): void
    {
        try {
            $version = $this->connection->fetchOne('SELECT VERSION()');
            if (!\is_string($version) || $version === '') {
                return;
            }

            $properties['database.type'] = mb_stripos($version, 'mariadb') !== false ? 'mariadb' : 'mysql';

            $majorMinor = self::majorMinor($version);
            if ($majorMinor !== null) {
                $properties['database.version_major_minor'] = $majorMinor;
            }
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string, bool|int|string> $properties
     */
    private function collectQueue(array &$properties): void
    {
        try {
            $types = [];
            foreach ($this->queueRegistry->all() as $adapter) {
                try {
                    $types[] = mb_strtolower($adapter->getType());
                } catch (\Throwable) {
                }
            }

            $types = array_values(array_unique(array_filter($types, static fn (string $type): bool => $type !== '')));
            sort($types);

            // Transport types only (e.g. "doctrine,redis"), never shop-specific queue names.
            $properties['queue.transports'] = implode(',', $types);
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string, bool|int|string> $properties
     */
    private function collectExtensions(array &$properties): void
    {
        try {
            $properties['shop.extensions_total'] = $this->count($this->pluginRepository, null);
            $properties['shop.extensions_active'] = $this->count($this->pluginRepository, true);
        } catch (\Throwable) {
        }

        try {
            $properties['shop.apps_total'] = $this->count($this->appRepository, null);
            $properties['shop.apps_active'] = $this->count($this->appRepository, true);
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string, bool|int|string> $properties
     */
    private function collectShopSizes(array &$properties): void
    {
        $tables = [
            'shop.products' => 'product',
            'shop.orders' => 'order',
            'shop.customers' => 'customer',
        ];

        foreach ($tables as $property => $table) {
            try {
                $count = $this->connection->fetchOne(\sprintf('SELECT COUNT(*) FROM `%s`', $table));
                $properties[$property] = self::bucket((int) $count);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * @param EntityRepository<covariant \Shopware\Core\Framework\DataAbstractionLayer\EntityCollection<covariant \Shopware\Core\Framework\DataAbstractionLayer\Entity>> $repository
     */
    private function count(EntityRepository $repository, ?bool $active): int
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        if ($active !== null) {
            $criteria->addFilter(new EqualsFilter('active', $active));
        }

        return $repository->searchIds($criteria, Context::createCLIContext())->getTotal();
    }

    private static function isApcuEnabled(): bool
    {
        // The task runs in a CLI worker where apcu_enabled() reflects apc.enable_cli,
        // so report the web-relevant apc.enabled flag instead.
        return \extension_loaded('apcu') && filter_var(\ini_get('apc.enabled'), \FILTER_VALIDATE_BOOLEAN);
    }

    private static function isOpcacheEnabled(): bool
    {
        if (!\extension_loaded('Zend OPcache')) {
            return false;
        }

        if (filter_var(\ini_get('opcache.enable'), \FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        if (\function_exists('opcache_get_status')) {
            $status = @opcache_get_status(false);

            return \is_array($status) && ($status['opcache_enabled'] ?? false) === true;
        }

        return false;
    }
}
