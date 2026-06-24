---
name: mrpbdepicking
description: "Verwenden wenn: Funktionen im mrpbdepicking-Modul implementiert werden; Picklistenlogik oder REST-API erweitert wird"
applyTo: "**/*.php"
---

# MrpBdePicking Implementierungsrichtlinien

Diese Regeln ergänzen die zentralen Richtlinien aus `../../../../../.github/instructions/php-dolibarr.instructions.md`.

## Modul-Schwerpunkte
- Fokus auf Picklistenfunktionen, MRP-Integration und API-Endpunkte.
- Bestehende Modulobjekte und etablierte API-Muster bevorzugen.

## API-Verhalten
- Antworten konsistent und vorhersehbar halten (Status, Fehlermeldungen, Datenstruktur).
- Rechte und Eingaben vor Schreiboperationen validieren.

## Datenzugriff
- Objektorientierte Aufrufe bevorzugen; direkte SQL nur, wenn keine geeignete Objektmethode existiert.
- Bei SQL immer `MAIN_DB_PREFIX`, Escaping und Entity-Filter berücksichtigen.

## CRUD-Rechteprüfung in Objektklassen (Pflicht)
- Jede schreibende Entität implementiert oder überschreibt `create()`, `update()` und `delete()`.
- `create()` und `update()` prüfen das passende `write`-Recht.
- `delete()` prüft das passende `delete`-Recht (oder dokumentierte Sonderrechte).
- Bei fehlender Berechtigung: `$this->error = 'NotEnoughPermissions'; return -1;`
- Danach Delegation an `createCommon()`, `updateCommon()` und `deleteCommon()`.

## REST-API-Standard (Pflicht, wenn API vorhanden)
- API-Klasse basiert auf `DolibarrApi`.
- Pro Entität: list/get/post/put/delete-Endpunkte bereitstellen.
- Jeder Endpunkt prüft Rechte vor der Operation.
- Ressourcenzugriff mit `_checkAccessToResource()` absichern.
- Schreiboperationen nur über Objektmethoden (`create/update/delete`) ausführen.
- Antworten mit `_cleanObjectDatas()` bereinigen.
- Fehler als `RestException` mit `400/403/404/500` behandeln.

## Rechte-Mapping dokumentieren (Pflicht)
- Für jede API-Operation dokumentieren, welches Recht erforderlich ist.
- Sonderfälle (z. B. gemeinsame Rechte für Dictionary-Endpunkte) explizit dokumentieren.
