export default {
    queue: 'bin/console messenger:consume async low_priority\n# Extension config examples:\n# monitorQueues = async, low_priority\n# monitorQueueGraceTimes = async:15, low_priority:60\n# monitorExcludeFailedQueues = true',
    symfony_scheduler:
        'bin/console debug:scheduler\nbin/console messenger:consume scheduler_<name>',
    'mysql-timezone':
        'mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root -p mysql',
    'composer-conflicts-repository':
        'composer config repositories.shopware-conflicts composer https://shopware.github.io/conflicts/\n\n# or add manually to composer.json:\n# "repositories": [\n#     {\n#         "type": "composer",\n#         "url": "https://shopware.github.io/conflicts/"\n#     }\n# ]',
    'admin-watcher':
        '# config/packages/shopware.yaml\nshopware:\n    admin_worker:\n        enable_admin_worker: false',
    'cache-compression-method':
        '# config/packages/shopware.yaml\nshopware:\n    cache:\n        cache_compression_method: zstd',
    'cart-compression-method':
        '# config/packages/shopware.yaml\nshopware:\n    cart:\n        compression_method: zstd',
    'cache-compression-method-extension-zstd': 'pecl install zstd',
    'cart-compression-method-extension-zstd': 'pecl install zstd',
    'app-url-check-disabled': 'APP_URL_CHECK_DISABLED=1',
    'symfony-secrets':
        '# config/packages/framework.yaml\nframework:\n    secrets:\n        enabled: false',
    mail_variables:
        '# config/packages/shopware.yaml\nshopware:\n    mail:\n        update_mail_variables_on_send: false',
    elasticsearch:
        'SHOPWARE_ES_ENABLED=1\nSHOPWARE_ES_INDEXING_ENABLED=1\nSHOPWARE_ES_HOSTS=localhost:9200',
    'fine-grained-caching':
        '# config/packages/shopware.yaml\nshopware:\n    cache:\n        tagging:\n            each_config: false\n            each_snippet: false\n            each_theme_config: false',
    'cache-id': 'SHOPWARE_CACHE_ID=my-stable-cache-id',
    'increment-storage':
        '# config/packages/shopware.yaml\nshopware:\n    increment:\n        user_activity:\n            type: array\n        message_queue:\n            type: array',
    business_logger:
        '# config/packages/prod/monolog.yaml\nmonolog:\n    handlers:\n        business_event_handler_buffer:\n            level: warning',
    mail: "# config/packages/framework.yaml\nframework:\n    messenger:\n        routing:\n            'Symfony\\Component\\Mailer\\Messenger\\SendEmailMessage': async",
    'messenger-auto-setup':
        'MESSENGER_TRANSPORT_DSN="...?auto_setup=false"\n\nbin/console messenger:setup-transports',
    sql_group_concat_max_len:
        '# my.cnf\n[mysqld]\ngroup_concat_max_len = 320000',
    sql_mode:
        '# my.cnf\n[mysqld]\nsql_mode = "STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION"',
    sql_time_zone: '# my.cnf\n[mysqld]\ndefault-time-zone = "+00:00"',
    sql_set_default_session_variables: 'SQL_SET_DEFAULT_SESSION_VARIABLES=0',
    'php.opcache.enable_file_override':
        '; php.ini\nopcache.enable_file_override=1',
    'zend.assertions': '; php.ini\nzend.assertions=-1',
    'php.opcache.interned_strings_buffer':
        '; php.ini\nopcache.interned_strings_buffer=20',
    'php.zend.detect_unicode': '; php.ini\nzend.detect_unicode=0',
    'php.zend.realpath_cache_ttl': '; php.ini\nrealpath_cache_ttl=3600',
    'product-stream-indexing':
        '# config/packages/shopware.yaml\nshopware:\n    product_stream:\n        indexing: false',
    'queue.adapter':
        'MESSENGER_TRANSPORT_DSN="redis://localhost:6379/messages"',
    'redis-tag-aware':
        "# config/packages/cache.yaml\nframework:\n    cache:\n        app: cache.adapter.redis_tag_aware\n        default_redis_provider: 'redis://localhost'\n        # optional: keep object cache on plain Redis if you prefer\n        pools:\n            cache.object:\n                adapter: cache.adapter.redis",
};
