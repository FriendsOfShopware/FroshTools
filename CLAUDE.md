# FroshTools - Claude Code Guidelines

## Datenbank-Aenderungen

**WICHTIG: Bei jeder Aenderung die die Datenbank betrifft, muss der Benutzer explizit darauf hingewiesen werden, BEVOR die Aenderung durchgefuehrt wird.**

Dazu gehoeren:
- Neue oder geaenderte Migrations (`src/Migration/`)
- Schema-Aenderungen (CREATE TABLE, ALTER TABLE, DROP TABLE, etc.)
- Daten-Manipulationen (INSERT, UPDATE, DELETE)
- Aenderungen an bestehenden Tabellen oder Spalten
- Aenderungen an Indizes oder Foreign Keys

Reine Lese-Operationen (SELECT, SHOW STATUS, information_schema Queries) sind davon ausgenommen und muessen nicht extra angekuendigt werden.

Der Benutzer muss die Datenbank-Aenderung ausdruecklich bestaetigen, bevor sie implementiert wird.
