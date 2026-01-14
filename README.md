# Frosh Tools

This plugin contains some utility functions for managing a Shopware 6 shop.

The current feature set consists of:

- **System-Status**
  - Checks PHP Version, MySQL, Queue is working etc.
  - Checks for performance optimizations and links documentation
- **Cache Manager**
  - Lists App and Http Cache and all folders in var/cache
  - Clear specific caches
  - Compile theme
- **Scheduled Task Manager**
  - Shows all Scheduled Tasks and can execute one specific
  - Edit interval and next execution
  - Register Scheduled Tasks
- **Queue Manager**
  - Shows the amount of messages in the queue
  - Reset queue
- **Elasticsearch Manager**
  - Shows the current status of the Elasticsearch nodes & cluster
  - Lists all indices of the Elasticsearch instance
  - Quick actions for index handling
  - Execute Elasticsearch console commands
- **Log Viewer**
  - Shows the entries of /var/log/*.log files
- **Shopware File Checker**
  - Checks if core files have been changed
- **State Machine Viewer**
  - basic view of order, transaction and delivery states
- **Override system config by config files**
  - Overwrite any system config value with static or environment values

## Installation

### Git
- Clone this repository into custom/plugins of your Shopware 6 installation
- Install composer dependencies `shopware-cli extension prepare custom/plugins/FroshTools`
- Build the assets with `shopware-cli extension build custom/plugins/FroshTools`

### Packagist
    composer require frosh/tools
    bin/console plugin:refresh
    bin/console plugin:install --activate FroshTools

### Store (Bearer token required from packages.shopware.com)
    composer require store.shopware.com/froshtools
    bin/console plugin:refresh
    bin/console plugin:install --activate FroshTools

## Commands

### `frosh:dev:robots-txt` - For testshops - add/change robots.txt to stop crawlers

```bash
bin/console frosh:dev:robots-txt
```

### `frosh:dev:robots-txt -r` - For testshops - revert changes in robots.txt

```bash
bin/console frosh:dev:robots-txt -r
```

### `frosh:composer-plugin:update` - update plugins managed by composer
```bash
bin/console frosh:composer-plugin:update
```

### `frosh:monitor` - Monitor your scheduled tasks and queue with this command and get notified via email.
```bash
bin/console frosh:monitor <sales-channel-id>
```

### `frosh:es:delete-unused-indices` - Delete unused Elasticsearch indices
```bash
bin/console frosh:es:delete-unused-indices
```

### `frosh:extension:checksum:check` - Check extension file integrity
```bash
bin/console frosh:extension:checksum:check [extension-name]
```

### `frosh:extension:checksum:create` - Create extension checksums
```bash
bin/console frosh:extension:checksum:create [extension-name]
```

### `frosh:redis-namespace:cleanup` - Clean up Redis namespaces (experimental)
```bash
bin/console frosh:redis-namespace:cleanup [--dry-run]
```

### `frosh:redis-namespace:list` - List Redis namespaces (experimental)
```bash
bin/console frosh:redis-namespace:list
```

### `frosh:redis-tag:cleanup` - Clean up Redis tags
```bash
bin/console frosh:redis-tag:cleanup
```

## Suppress files from being restorable in FileChecker

```yaml
# config/packages/frosh_tools.yaml
frosh_tools:
    file_checker:
        exclude_files:
            - vendor/shopware/core/FirstFile.php
            - vendor/shopware/core/SecondFile.php
```

## Screenshots

![System Status](https://i.imgur.com/tKVIvFh.png)
![Cache Manager](https://i.imgur.com/9aIpljE.png)
![Scheduled Task Manager](https://i.imgur.com/osXwRgk.png)
![Queue Manager](https://i.imgur.com/Jca0Diw.png)
![Log Viewer](https://i.imgur.com/521xMdS.png)
![File Checker](https://i.imgur.com/WslZDJ3.png)
![Elasticsearch Manager](https://i.imgur.com/BtU7jTu.png)
![Feature Flags](https://i.imgur.com/VL0gLeM.png)
![State Machine Viewer](https://i.imgur.com/LAsbFMY.png)
