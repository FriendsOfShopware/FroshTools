<?php

declare(strict_types=1);

namespace Frosh\Tools\Tests\Controller;

use Frosh\Tools\Acl\FroshToolsPrivileges;
use Frosh\Tools\Controller\CacheController;
use Frosh\Tools\Controller\ComposerAuditController;
use Frosh\Tools\Controller\ElasticsearchController;
use Frosh\Tools\Controller\ExtensionFilesController;
use Frosh\Tools\Controller\FastlyController;
use Frosh\Tools\Controller\FeatureFlagController;
use Frosh\Tools\Controller\HealthController;
use Frosh\Tools\Controller\LogController;
use Frosh\Tools\Controller\QueueController;
use Frosh\Tools\Controller\ScheduledTaskController;
use Frosh\Tools\Controller\SecurityController;
use Frosh\Tools\Controller\ShopmonController;
use Frosh\Tools\Controller\ShopwareFilesController;
use Frosh\Tools\Controller\StatisticsController;
use Frosh\Tools\Controller\SymfonySchedulerController;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;

#[CoversNothing]
class AclRouteTest extends TestCase
{
    /**
     * @param class-string $class
     */
    #[DataProvider('routes')]
    public function testRouteRequiresExpectedPrivilege(string $class, string $method, string $privilege): void
    {
        static::assertSame([$privilege], $this->resolveAcl($class, $method));
    }

    public function testEveryControllerActionIsCovered(): void
    {
        $covered = [];
        foreach (self::routes() as $row) {
            $covered[$row[0] . '::' . $row[1]] = true;
        }

        foreach ($this->controllerClasses() as $class) {
            $reflection = new \ReflectionClass($class);
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }

                if ($method->getAttributes(Route::class) === []) {
                    continue;
                }

                $key = $class . '::' . $method->getName();
                static::assertArrayHasKey($key, $covered, \sprintf('Add %s to AclRouteTest::routes()', $key));
            }
        }
    }

    /**
     * @return iterable<string, array{class-string, string, string}>
     */
    public static function routes(): iterable
    {
        yield 'health.status' => [HealthController::class, 'status', FroshToolsPrivileges::READ];
        yield 'health.performance' => [HealthController::class, 'performanceStatus', FroshToolsPrivileges::READ];
        yield 'health.ping' => [HealthController::class, 'pingStatus', FroshToolsPrivileges::READ];
        yield 'statistics.cache' => [StatisticsController::class, 'cacheStatistics', FroshToolsPrivileges::READ];
        yield 'statistics.database' => [StatisticsController::class, 'databaseStatistics', FroshToolsPrivileges::READ];
        yield 'feature-flags.list' => [FeatureFlagController::class, 'list', FroshToolsPrivileges::READ];

        yield 'cache.list' => [CacheController::class, 'cacheStatistics', FroshToolsPrivileges::CACHE_READ];
        yield 'cache.clear' => [CacheController::class, 'clearCache', FroshToolsPrivileges::CACHE_UPDATE];
        yield 'cache.opcache' => [CacheController::class, 'clearOpCache', FroshToolsPrivileges::CACHE_UPDATE];

        yield 'queue.transports' => [QueueController::class, 'transports', FroshToolsPrivileges::QUEUE_READ];
        yield 'queue.messages' => [QueueController::class, 'messages', FroshToolsPrivileges::QUEUE_READ];
        yield 'queue.list' => [QueueController::class, 'list', FroshToolsPrivileges::QUEUE_READ];
        yield 'queue.retry' => [QueueController::class, 'retryMessage', FroshToolsPrivileges::QUEUE_UPDATE];
        yield 'queue.delete' => [QueueController::class, 'deleteMessage', FroshToolsPrivileges::QUEUE_UPDATE];
        yield 'queue.purge' => [QueueController::class, 'purgeTransport', FroshToolsPrivileges::QUEUE_UPDATE];
        yield 'queue.reset' => [QueueController::class, 'resetQueue', FroshToolsPrivileges::QUEUE_UPDATE];

        yield 'scheduled.run' => [ScheduledTaskController::class, 'runTask', FroshToolsPrivileges::SCHEDULED_TASK_UPDATE];
        yield 'scheduled.schedule' => [ScheduledTaskController::class, 'scheduleTask', FroshToolsPrivileges::SCHEDULED_TASK_UPDATE];
        yield 'scheduled.deactivate' => [ScheduledTaskController::class, 'deactivateTask', FroshToolsPrivileges::SCHEDULED_TASK_UPDATE];
        yield 'scheduled.register' => [ScheduledTaskController::class, 'registerTasks', FroshToolsPrivileges::SCHEDULED_TASK_UPDATE];
        yield 'scheduler.list' => [SymfonySchedulerController::class, 'list', FroshToolsPrivileges::SCHEDULED_TASK_READ];
        yield 'scheduler.run' => [SymfonySchedulerController::class, 'runTask', FroshToolsPrivileges::SCHEDULED_TASK_UPDATE];

        yield 'es.status' => [ElasticsearchController::class, 'status', FroshToolsPrivileges::ELASTICSEARCH_READ];
        yield 'es.indices' => [ElasticsearchController::class, 'indices', FroshToolsPrivileges::ELASTICSEARCH_READ];
        yield 'es.unused' => [ElasticsearchController::class, 'unusedIndices', FroshToolsPrivileges::ELASTICSEARCH_READ];
        yield 'es.orphaned' => [ElasticsearchController::class, 'orphanedIndices', FroshToolsPrivileges::ELASTICSEARCH_READ];
        yield 'es.delete' => [ElasticsearchController::class, 'deleteIndex', FroshToolsPrivileges::ELASTICSEARCH_UPDATE];
        yield 'es.console' => [ElasticsearchController::class, 'console', FroshToolsPrivileges::ELASTICSEARCH_UPDATE];
        yield 'es.flush' => [ElasticsearchController::class, 'flushAll', FroshToolsPrivileges::ELASTICSEARCH_UPDATE];
        yield 'es.reindex' => [ElasticsearchController::class, 'reindex', FroshToolsPrivileges::ELASTICSEARCH_UPDATE];
        yield 'es.alias' => [ElasticsearchController::class, 'switchAlias', FroshToolsPrivileges::ELASTICSEARCH_UPDATE];
        yield 'es.cleanup' => [ElasticsearchController::class, 'deleteUnusedIndices', FroshToolsPrivileges::ELASTICSEARCH_UPDATE];
        yield 'es.cleanup-orphaned' => [ElasticsearchController::class, 'deleteOrphanedIndices', FroshToolsPrivileges::ELASTICSEARCH_UPDATE];
        yield 'es.reset' => [ElasticsearchController::class, 'reset', FroshToolsPrivileges::ELASTICSEARCH_UPDATE];

        yield 'logs.files' => [LogController::class, 'getLogFiles', FroshToolsPrivileges::LOGS_READ];
        yield 'logs.file' => [LogController::class, 'getLog', FroshToolsPrivileges::LOGS_READ];

        yield 'security.status' => [SecurityController::class, 'status', FroshToolsPrivileges::SECURITY_READ];
        yield 'security.sbom' => [SecurityController::class, 'sbom', FroshToolsPrivileges::SECURITY_READ];
        yield 'security.audit' => [ComposerAuditController::class, 'audit', FroshToolsPrivileges::SECURITY_READ];
        yield 'security.extensions' => [ExtensionFilesController::class, 'listExtensionFiles', FroshToolsPrivileges::SECURITY_READ];
        yield 'security.files' => [ShopwareFilesController::class, 'listShopwareFiles', FroshToolsPrivileges::SECURITY_READ];
        yield 'security.contents' => [ShopwareFilesController::class, 'getFileContents', FroshToolsPrivileges::SECURITY_READ];
        yield 'security.restore' => [ShopwareFilesController::class, 'restoreShopwareFile', FroshToolsPrivileges::SECURITY_UPDATE];

        yield 'fastly.status' => [FastlyController::class, 'status', FroshToolsPrivileges::FASTLY_READ];
        yield 'fastly.stats' => [FastlyController::class, 'statistics', FroshToolsPrivileges::FASTLY_READ];
        yield 'fastly.snippets' => [FastlyController::class, 'snippets', FroshToolsPrivileges::FASTLY_READ];
        yield 'fastly.purge' => [FastlyController::class, 'purge', FroshToolsPrivileges::FASTLY_UPDATE];
        yield 'fastly.purge-all' => [FastlyController::class, 'purgeAll', FroshToolsPrivileges::FASTLY_UPDATE];

        yield 'shopmon.status' => [ShopmonController::class, 'status', FroshToolsPrivileges::SHOPMON_READ];
        yield 'shopmon.setup' => [ShopmonController::class, 'setup', FroshToolsPrivileges::SHOPMON_UPDATE];
        yield 'shopmon.remove' => [ShopmonController::class, 'remove', FroshToolsPrivileges::SHOPMON_UPDATE];
    }

    /**
     * @return list<class-string>
     */
    private function controllerClasses(): array
    {
        $classes = [];
        foreach (glob(\dirname(__DIR__, 2) . '/src/Controller/*Controller.php') ?: [] as $file) {
            $classes[] = 'Frosh\\Tools\\Controller\\' . basename($file, '.php');
        }

        sort($classes);

        return $classes;
    }

    /**
     * @param class-string $class
     *
     * @return list<string>
     */
    private function resolveAcl(string $class, string $method): array
    {
        $classAcl = $this->aclFromAttributes((new \ReflectionClass($class))->getAttributes(Route::class));
        $methodAcl = $this->aclFromAttributes((new \ReflectionMethod($class, $method))->getAttributes(Route::class));

        return $methodAcl ?? $classAcl ?? [];
    }

    /**
     * @param list<\ReflectionAttribute<Route>> $attributes
     *
     * @return list<string>|null
     */
    private function aclFromAttributes(array $attributes): ?array
    {
        foreach ($attributes as $attribute) {
            $acl = $attribute->newInstance()->defaults['_acl'] ?? null;
            if (\is_array($acl)) {
                return array_values($acl);
            }
        }

        return null;
    }
}
