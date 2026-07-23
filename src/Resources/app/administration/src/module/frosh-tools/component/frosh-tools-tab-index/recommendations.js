/**
 * Inline explanations for the health & performance checks.
 *
 * Keyed by the `id` of the SettingsResult that the backend returns. Each entry
 * provides the context that previously lived behind an external documentation
 * link, so the information can be shown inline in a modal instead of sending the
 * user to developer.shopware.com or docs.shopware.com.
 *
 * Shape per entry:
 *   description: why the check matters / what the finding means (required)
 *   solution:    how to resolve it (optional)
 *   code:        a config/ini snippet illustrating the fix (optional)
 */
export default {
    // --- Health ---------------------------------------------------------
    'multiple-autoloaders': {
        description:
            'More than one Composer autoloader is registered in the running process. This usually happens when a plugin ships its own "vendor/" directory: its dependencies get loaded a second time, Composer can no longer track installed versions reliably, and you can run into duplicate-class or wrong-version bugs that are hard to diagnose.',
        solution:
            'Remove the bundled "vendor/" directories so only the project autoloader is used. If a plugin requires its own dependencies, ask its author to declare them as Composer requirements instead of shipping a vendor folder.',
    },
    queue: {
        description:
            'Messages have been waiting in the message queue longer than the configured grace time. That almost always means no worker is consuming the queue, so asynchronous tasks (mails, indexing, …) pile up.',
        solution:
            'Make sure a CLI worker is running and supervised (e.g. via systemd or Supervisor) so the queue is drained continuously.',
        code: 'bin/console messenger:consume async low_priority failed',
    },
    'mysql-timezone': {
        description:
            'The MySQL time zone tables are not loaded, so CONVERT_TZ() and named time zones (e.g. "Europe/Berlin") do not work. Features that rely on time zone conversion can silently return wrong results.',
        solution:
            'Import the zoneinfo data into MySQL once on the database server:',
        code: 'mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root -p mysql',
    },
    'security-eol-shopware': {
        description:
            'Your Shopware version is approaching, or has already passed, its security end-of-life. Once security support ends you no longer receive security fixes, which is a real risk for a production shop.',
        solution:
            'Plan an update to a still-supported version, ideally the latest LTS release.',
    },
    'system-time': {
        description:
            'The server clock differs noticeably from real time. A drifting clock breaks time-sensitive features such as TOTP/2FA, OAuth tokens, scheduled tasks and signed requests.',
        solution:
            'Enable automatic time synchronisation (NTP/chrony) on the host so the system clock stays accurate.',
    },
    'php-fpm-max-listen-queue': {
        description:
            'Requests have had to wait in the PHP-FPM listen queue because no worker was free. A value above 0 indicates the pool is occasionally too small for your peak load.',
        solution:
            'Increase the number of PHP-FPM workers (pm.max_children and the related pm.* settings) for the pool, making sure the host has enough memory for the extra workers.',
    },
    'php-fpm-max-children-reached': {
        description:
            'PHP-FPM has hit its pm.max_children limit, meaning all workers were busy and additional requests had to wait. A value above 0 points to a pool that is too small for peak traffic.',
        solution:
            'Raise pm.max_children for the FPM pool (and verify the host has enough RAM to back the additional workers).',
    },
    'php-fpm-memory-peak': {
        description:
            'The highest amount of memory a single PHP-FPM worker has used. This is informational — use it to size memory_limit and to decide how many workers (pm.max_children) fit into the available RAM.',
    },
    'composer-conflicts-repository': {
        description:
            'The Shopware conflicts repository (https://shopware.github.io/conflicts/) is not registered in the "repositories" section of your root composer.json. This repository declares combinations of packages and versions that are known to be incompatible. With it in place, Composer refuses to install or update to a broken combination before it reaches production; without it those guard rails are missing.',
        solution:
            'Add the conflicts repository to the "repositories" section of your root composer.json (either edit the file directly or run the composer command below), then run "composer update" so Composer re-evaluates your dependencies against it.',
        code: 'composer config repositories.shopware-conflicts composer https://shopware.github.io/conflicts/\n\n# or add manually to composer.json:\n# "repositories": [\n#     {\n#         "type": "composer",\n#         "url": "https://shopware.github.io/conflicts/"\n#     }\n# ]',
    },

    // --- Performance ----------------------------------------------------
    'admin-watcher': {
        description:
            'The admin worker processes the message queue from within the administration in the browser and keeps a request open for every logged-in admin user. It does not scale and competes with regular traffic.',
        solution:
            'Disable the admin worker and run the queue via supervised CLI workers instead.',
        code: '# config/packages/shopware.yaml\nshopware:\n    admin_worker:\n        enable_admin_worker: false',
    },
    'cache-compression-method': {
        description:
            'Cache entries are compressed with gzip. zstd compresses faster and produces smaller payloads, which lowers both CPU usage and the size stored in the cache backend.',
        solution:
            'Switch the cache compression method to zstd (requires the PHP "zstd" extension).',
        code: '# config/packages/shopware.yaml\nshopware:\n    cache:\n        cache_compression_method: zstd',
    },
    'cart-compression-method': {
        description:
            'The cart is compressed with gzip. zstd compresses faster and produces smaller payloads, lowering CPU usage and the storage footprint of persisted carts.',
        solution:
            'Switch the cart compression method to zstd (requires the PHP "zstd" extension).',
        code: '# config/packages/shopware.yaml\nshopware:\n    cart:\n        compression_method: zstd',
    },
    'cache-compression-method-extension-zstd': {
        description:
            'The cache compression method is set to zstd, but the PHP "zstd" extension is not installed. Compression will fail until the extension is available.',
        solution:
            'Install and enable the PHP zstd extension, or switch the compression method back to gzip.',
        code: 'pecl install zstd',
    },
    'cart-compression-method-extension-zstd': {
        description:
            'The cart compression method is set to zstd, but the PHP "zstd" extension is not installed. Compression will fail until the extension is available.',
        solution:
            'Install and enable the PHP zstd extension, or switch the compression method back to gzip.',
        code: 'pecl install zstd',
    },
    'app-url-check-disabled': {
        description:
            'On every request Shopware verifies that the configured APP_URL matches the incoming request. On a correctly configured host this check is redundant and only adds overhead.',
        solution:
            'Disable the external APP_URL check via an environment variable once you are sure APP_URL is configured correctly.',
        code: 'APP_URL_CHECK_DISABLED=1',
    },
    'symfony-secrets': {
        description:
            "Symfony's secrets vault is initialised on every request. If you do not use encrypted Symfony secrets, disabling the vault removes that per-request overhead.",
        solution: 'Disable the secrets vault if you do not rely on it.',
        code: '# config/packages/framework.yaml\nframework:\n    secrets:\n        enabled: false',
    },
    mail_variables: {
        description:
            'By default Shopware writes mail template variables back to the database after each mail is sent. On shops that send many mails this causes a noticeable amount of extra write load.',
        solution: 'Disable updating mail variables on send.',
        code: '# config/packages/shopware.yaml\nshopware:\n    mail:\n        update_mail_variables_on_send: false',
    },
    elasticsearch: {
        description:
            'Elasticsearch/OpenSearch is not enabled. For larger catalogs it offloads search and listing queries from MySQL, which significantly improves response times and reduces database load.',
        solution:
            'Set up an Elasticsearch/OpenSearch server and enable it via the environment, then build the index.',
        code: 'SHOPWARE_ES_ENABLED=1\nSHOPWARE_ES_INDEXING_ENABLED=1\nSHOPWARE_ES_HOSTS=localhost:9200',
    },
    'fine-grained-caching': {
        description:
            'Fine-grained cache tagging creates a separate cache tag for every config, snippet and theme config value. With Redis or Varnish this bloats tag storage and makes invalidation more expensive.',
        solution: 'Disable fine-grained caching.',
        code: '# config/packages/shopware.yaml\nshopware:\n    cache:\n        tagging:\n            each_config: false\n            each_snippet: false\n            each_theme_config: false',
    },
    'cache-id': {
        description:
            'No fixed cache id is set. Without it the cache namespace changes on every deployment or cache clear, so caches cannot be reused across deployments or shared between cluster nodes.',
        solution:
            'Set a stable SHOPWARE_CACHE_ID (identical across all nodes of a cluster). Note: this setting was removed in Shopware 6.7.',
        code: 'SHOPWARE_CACHE_ID=my-stable-cache-id',
    },
    'increment-storage': {
        description:
            'The increment storage (user activity and message-queue statistics) is backed by MySQL, which generates a large number of small writes. Using "array" or Redis scales much better.',
        solution:
            'Move the increment storage to Redis (or "array" if you do not need it persisted).',
        code: '# config/packages/shopware.yaml\nshopware:\n    increment:\n        user_activity:\n            type: array\n        message_queue:\n            type: array',
    },
    business_logger: {
        description:
            'The business event handler logs below WARNING level. On a busy shop this writes a large volume of low-value log entries, adding I/O and storage overhead.',
        solution:
            'Raise the business event handler log level to at least WARNING via your Monolog configuration.',
        code: '# config/packages/prod/monolog.yaml\nmonolog:\n    handlers:\n        business_event_handler_buffer:\n            level: warning',
    },
    mail: {
        description:
            'Mails are sent synchronously during the request. The customer then has to wait for the SMTP server to respond. Routing mail through the message queue sends it in the background.',
        solution:
            'Route the SendEmailMessage onto the asynchronous transport so mails are delivered by the worker.',
        code: "# config/packages/framework.yaml\nframework:\n    messenger:\n        routing:\n            'Symfony\\Component\\Mailer\\Messenger\\SendEmailMessage': async",
    },
    'messenger-auto-setup': {
        description:
            'The messenger transport has auto_setup enabled, so it checks (and potentially creates) its queue/table on every connect. On production this is unnecessary overhead.',
        solution:
            'Append "auto_setup=false" to the transport DSN(s) and create the transports once via the console.',
        code: 'MESSENGER_TRANSPORT_DSN="...?auto_setup=false"\n\nbin/console messenger:setup-transports',
    },
    sql_group_concat_max_len: {
        description:
            "MySQL's group_concat_max_len is too low. Shopware aggregates data with GROUP_CONCAT and a low limit silently truncates the result, which leads to broken data or errors.",
        solution: 'Raise group_concat_max_len on the MySQL server.',
        code: '# my.cnf\n[mysqld]\ngroup_concat_max_len = 320000',
    },
    sql_mode: {
        description:
            "MySQL's sql_mode contains ONLY_FULL_GROUP_BY, which rejects some of the grouped queries Shopware relies on and can cause runtime errors.",
        solution: 'Remove ONLY_FULL_GROUP_BY from the server sql_mode.',
        code: '# my.cnf\n[mysqld]\nsql_mode = "STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION"',
    },
    sql_time_zone: {
        description:
            'The database is not running in UTC. Shopware stores timestamps in UTC, so a different database time zone leads to wrong dates and times.',
        solution: 'Set the MySQL time_zone to UTC.',
        code: '# my.cnf\n[mysqld]\ndefault-time-zone = "+00:00"',
    },
    sql_set_default_session_variables: {
        description:
            'Shopware sets default session variables on every new database connection, adding a round-trip per connect. Configuring the equivalents on the server avoids this. (Relevant before Shopware 6.7.)',
        solution:
            'Disable the per-connect session variables and apply the equivalent settings in your MySQL server configuration instead.',
        code: 'SQL_SET_DEFAULT_SESSION_VARIABLES=0',
    },
    'php.opcache.enable_file_override': {
        description:
            'opcache.enable_file_override lets OPcache answer file_exists()/is_file()/is_readable() from the cache, reducing filesystem calls on every request.',
        solution: 'Enable it in your PHP configuration.',
        code: '; php.ini\nopcache.enable_file_override=1',
    },
    'zend.assertions': {
        description:
            'Assertions should be compiled out in production. Any value other than -1 means assertion code is still compiled and executed, wasting CPU.',
        solution: 'Disable assertions in your production PHP configuration.',
        code: '; php.ini\nzend.assertions=-1',
    },
    'php.opcache.interned_strings_buffer': {
        description:
            'The OPcache interned strings buffer is small. Shopware uses a lot of strings, so a small buffer reduces OPcache efficiency and increases memory churn.',
        solution:
            'Increase the interned strings buffer in your PHP configuration.',
        code: '; php.ini\nopcache.interned_strings_buffer=20',
    },
    'php.zend.detect_unicode': {
        description:
            'zend.detect_unicode makes PHP scan the beginning of every file for a BOM. Disabling it avoids that unnecessary work.',
        solution: 'Disable it in your PHP configuration.',
        code: '; php.ini\nzend.detect_unicode=0',
    },
    'php.zend.realpath_cache_ttl': {
        description:
            'A low realpath_cache_ttl causes PHP to re-resolve and stat file paths frequently, increasing filesystem load on every request.',
        solution: 'Increase the realpath cache TTL in your PHP configuration.',
        code: '; php.ini\nrealpath_cache_ttl=3600',
    },
    'product-stream-indexing': {
        description:
            'Product stream indexing keeps a pre-computed mapping of products to dynamic product groups up to date. On many shops this background work is unnecessary and can be disabled.',
        solution:
            'Disable product stream indexing if you do not depend on the pre-computed mapping.',
        code: '# config/packages/shopware.yaml\nshopware:\n    product_stream:\n        indexing: false',
    },
    'queue.adapter': {
        description:
            'The message queue uses a transport that is not suited for production. The database (doctrine) transport does not scale well once multiple workers consume it, and the "sync" transport executes jobs inside the web request instead of in the background.',
        solution:
            'Use a dedicated queue backend such as Redis or RabbitMQ for the messenger transport.',
        code: 'MESSENGER_TRANSPORT_DSN="redis://localhost:6379/messages"',
    },
    'redis-tag-aware': {
        description:
            'This check looks at the HTTP cache pool (cache.http) only — not cache.object or other pools. The plain Redis adapter stores cache tags inefficiently; RedisTagAware is optimised for Shopware’s tag-based HTTP cache invalidation. Using plain Redis for cache.object on purpose is fine and will not trigger this warning.',
        solution:
            'Configure the HTTP cache pool (or the default app adapter that cache.http inherits) to use the TagAware Redis adapter.',
        code: "# config/packages/cache.yaml\nframework:\n    cache:\n        app: cache.adapter.redis_tag_aware\n        default_redis_provider: 'redis://localhost'\n        # optional: keep object cache on plain Redis if you prefer\n        pools:\n            cache.object:\n                adapter: cache.adapter.redis",
    },
};
