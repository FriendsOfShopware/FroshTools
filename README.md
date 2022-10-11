# Frosh Tools

[![Open in Gitpod](https://gitpod.io/button/open-in-gitpod.svg)](https://gitpod.io/#https://github.com/FriendsOfShopware/FroshTools)

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
  - Edit intervall and next execution
  - Register Scheduled Tasks
- **Queue Manager**
  - Shows the amount of messages in the queue
  - Reset queue
- **Log viewer**
  - Shows the entries of /var/log/*.log files
- **Task Logging**
  - Can be enabled with env `FROSH_TOOLS_TASK_LOGGING=1` in `.env`. This will create a log in `var/log/task_logging-xx.log`
    - Set `FROSH_TOOLS_TASK_LOGGING_INFO=1` in `.env` to log all tasks
- **Feature Flag Manager**
  - Provides the ability to enable or disable feature flags
- **State Machine Visualisation**
  - basic view of order, transaction and delivery states

## Installation

### Git
- Clone this repository into custom/plugins of your Shopware 6 installation
- Install composer dependencies `shopware-cli extension prepare custom/plugins/FroshTools`
- Build the assets with `shopware-cli extension build custom/plugins/FroshTools`

### Store (Bearer token required from packages.shopware.com)
    composer require store.shopware.com/froshtools

## Commands

### `frosh:env:list` - Listing of all environment variables

`bin/console frosh:env:list`

`bin/console frosh:env:list --json`
Lists as json output

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

### `frosh:dev:robots-txt` - For testshops - add/change robots.txt to stop crawers

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