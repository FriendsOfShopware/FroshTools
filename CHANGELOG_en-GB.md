# 0.1.0

* First release in Store

# 0.1.1

* Added positive status message for the production mode check. 
* Administration view improvements.
* Added Max-Execution-Time check to system status.
* Adjusted sorting of Tasks and Caches.
* Added buttons to refresh data in all components.
* Added Diff-Function in Shopware-Files-Checker.
* Added Restore-Function in Shopware-Files-Checker.
* Added Feature Flag Manager

# 0.1.2

* Exclude inactive tasks from health check
* Sort feature-flags, add refresh-button onto feature-flags tab
* Added support for the new message incrementer

# 0.1.3

* New command "frosh:dev:robots-txt" to stop crawers in test environments
* New command "frosh:plugin:update" to update plugins with available updates at once
* Improve url of files in Shopware-Files-Checker

# 0.1.4

* New command "frosh:composer-plugin:update" to update plugins installed with composer
* Show IndexerName for TaskLogger
* Add manager for Elasticsearch
* Add performance checks

# 0.1.5

* Fix QueueChecker to show correct message

# 0.1.6

* Show formatted date in logs
* Correct empty Console body for newer Elasticsearch version

# 0.1.7

* Fixed theme compile button in Administration

# 0.2.0

* Fixed reschedule Task to not stack the nextExecutionTime
* Added env FROSH_TOOLS_TASK_LOGGING_INFO to log everything in tasks
* Changed FROSH_TOOLS_TASK_LOGGING to just log exceptions
* Fixed recommended value for IncrementStorage

# 0.2.1

* Fixed support to Enterprise Search

# 0.2.2

* Added more recommendations for performance
* Fix Redis for newer shopware versions

# 0.2.3

* Fix deprecation messages with 6.4.11 and higher for api
* Fix ini-check not to throw error when is has not set

# 0.2.4

* Fix support for restricted environments

# 0.2.5
* Fix wrong ini-check for 0-value
* Added modal to check message in log viewer
* Added State Machine Viewer

# 0.2.6
* Change warning of local filesystem into info
* Change warning of missing ElasticSearch into info
* Add Button to reregister Scheduled Tasks
* Optimize performance for checking modified shopware files

# 0.2.7
* Fixed reporting of scheduled task did not run on cache invalidate task
* Switched to shopware-cli
* Optimized datetime view in scheduled task
* Added simple maintainance command

# 0.2.8
* Improve Elasticsearch usage

# 0.2.9

* Health checker / Performance Status results are now always in English to make it easier to integrate in external systems
* PHP 8.1 is now the recommended PHP version. PHP 8.0 will be a warning
* MySQL 8 is now checked in performance checker
* Optimized UI

# 0.2.10

* Fix labels of php checker and max execution time

# 0.2.11

* Added ACL for FroshTools. You need to maybe adjust your ACL roles

# 0.2.12

* Fixed formatting of file size in the cache tab

# 0.2.13

* Added check for active pcre-jit
* Fixed health-check with no permission

# 0.2.14

* Added lightning css to improve theme compiling performance and reduce css size

To enable to create a `config/packages/frosh_tools.yaml` with the following content:

```yaml
frosh_tools:
    storefront:
        lightningcss:
            enabled: true
```

# 0.2.15

* Adjusted default browserlist to `defaults`

# 0.2.16

* Fixed a problem when the old Symfony Container was used with the newer plugin version

# 0.2.17

* Improved Symfony Container creation to fix a bug when a container parameter is missing

# 0.2.18

* Fix queue checker with timezones
* Fix reset queue to clear up product_export
