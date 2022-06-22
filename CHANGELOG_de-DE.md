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
