# Frosh Tools

This plugin contains some utility functions for managing a Shopware 6 shop.

The current feature set consists of:

- **System Status**
  - Checks PHP Version, MySQL, Queue is working etc.
  - Checks for performance optimizations and links documentation
- **Cache manager**
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
- **Log viewer**
  - Shows the entries of /var/log/*.log files
- **Task Logging**
  - Can be enabled with env `FROSH_TOOLS_TASK_LOGGING=1` in `.env`. This will create a log in `var/log/task_logging-xx.log`
    - Set `FROSH_TOOLS_TASK_LOGGING_INFO=1` in `.env` to log all tasks
- **Shopware file checker**
  - Checks if core files have been changed
- **State Machine Visualisation**
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

### Store (Bearer token required from packages.shopware.com)
    composer require store.shopware.com/froshtools

## Commands

### `frosh:env:list` - Listing of all environment variables
```bash
bin/console frosh:env:list
```
Lists as json output:
```bash
bin/console frosh:env:list --json
```

### `frosh:env:get` - Get environment variables

```bash
bin/console frosh:env:get APP_URL
http://localhost
```

```bash
bin/console frosh:env:get APP_URL --key-value
APP_URL=http://localhost
```

```bash
bin/console frosh:env:get APP_URL --json
{
    "APP_URL": "http:\/\/localhost"
}
```

### `frosh:env:set` - Set environment variables

```bash
bin/console frosh:env:set VARIABLE VALUE
```

### `frosh:env:del` - Delete environment variables

```bash
bin/console frosh:env:del VARIABLE
```

### `frosh:dev:robots-txt` - For testshops - add/change robots.txt to stop crawlers

```bash
bin/console frosh:dev:robots-txt
```

### `frosh:dev:robots-txt -r` - For testshops - revert changes in robots.txt

```bash
bin/console frosh:dev:robots-txt -r
```

### `frosh:plugin:update` - update plugins with available updates at once

```bash
bin/console frosh:plugin:update
```

### `frosh:composer-plugin:update` - update plugins managed by composer
```bash
bin/console frosh:composer-plugin:update
```

### `frosh:user:change:password` - updates user password
```bash
bin/console frosh:user:change:password <username> [<password>]
```

### `frosh:monitor` - Monitor your scheduled tasks and queue with this command and get notified via email.
```bash
bin/console frosh:monitor <sales-channel-id>
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

## Override system_config with config files

```yaml
# config/packages/frosh_tools.yaml
frosh_tools:
    system_config:
        default:
            core.listing.allowBuyInListing: true
```

The key `default` is the sales channel scope, default is `null` which is the global scope. You can specify a specific salesChannelId to overwrite the value

```yaml
# config/packages/frosh_tools.yaml
frosh_tools:
    system_config:
        default:
            core.listing.allowBuyInListing: true
        # Disable it for the specific sales channel
        0188da12724970b9b4a708298259b171:
            core.listing.allowBuyInListing: false
```

As it is a normal Symfony config, you can of course use also environment variables

```yaml
# config/packages/frosh_tools.yaml
frosh_tools:
    system_config:
        default:
            core.listing.allowBuyInListing: '%env(bool:ALLOW_BUY_IN_LISTING)%'
```

```
# .env.local
ALLOW_BUY_IN_LISTING=true
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
