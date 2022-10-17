# 0.1.0

* Erster Release im Store

# 0.1.1

* Positive Statusnachricht für den Produktivmodus Check hinzugefügt. 
* Verbesserungen der Administration-Oberfläche.
* Max-Execution-Time Prüfung zum System Status hinzugefügt.
* Sortierung von Geplanten Aufgaben und Caches angepasst
* Schaltflächen zum Aktualisieren von Daten in allen Komponenten hinzugefügt
* Vergleichen-Funktion im Shopware-Files-Checker hinzugefügt
* Wiederherstellen-Funktion im Shopware-Files-Checker hinzugefügt
* Feature Flag Manager hinzugefügt

# 0.1.2

* Inaktive Aufgaben vom Task-Manager ausgeschloßen
* Feature-flags sortiert und einen Aktualisieren-Button hinzugefügt
* Support des neuen Incrementer für die Messages hinzugefügt

# 0.1.3

* Neuer Befehl "frosh:dev:robots-txt" um in Testumgebungen Bots auszuschließen
* Neuer Befehl "frosh:plugin:update" um alle Plugins mit Updates automatisch zu aktualisieren
* Verbessere URL von Dateien im Shopware-Files-Checker

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