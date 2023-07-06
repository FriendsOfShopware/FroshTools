Dieses Plugin enthält einige nützliche Funktionen für die Verwaltung eines Shopware 6 Shops.

Der aktuelle Funktionsumfang besteht aus:

*   System-Status
    *   Prüft PHP Version, MySQL, Queue funktioniert etc.
*   Cache-Verwaltung
    *   Listet App und Http Cache und alle Ordner in var/cache auf
*   Geplanter Task-Manager
    *   Zeigt alle geplanten Tasks und kann einen bestimmten ausführen
*   Warteschlangen-Manager
    *   Zeigt die Anzahl der Nachrichten in der Warteschlange an
*   Log-Viewer
    *   Zeigt die Einträge der Dateien /var/log/*.log an
*   Task-Protokollierung
    *   Kann mit FROSH_TOOLS_TASK_LOGGING=1 in .env aktiviert werden. Dadurch wird ein Protokoll in var/log/task_logging-xx.log erstellt.
        *   Mit FROSH_TOOLS_TASK_LOGGING_INFO=1 in .env werden alle Tasks geloggt.
*   Feature Flag Manager
    *   Erlaubt das aktivieren/deaktivieren von Feature Flags

Link to repository: [https://github.com/FriendsOfShopware/FroshTools](https://github.com/FriendsOfShopware/FroshTools)  

Dieses Plugin wird von [@FriendsOfShopware](https://store.shopware.com/friends-of-shopware.html) entwickelt.  
Maintainer dieses Plugins ist: [Soner Sayakci](https://github.com/shyim)

Bei Fragen / Fehlern bitte ein [Github Issue](https://github.com/FriendsOfShopware/FroshTools/issues) erstellen
