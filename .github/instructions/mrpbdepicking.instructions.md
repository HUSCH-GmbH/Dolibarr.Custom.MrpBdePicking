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
