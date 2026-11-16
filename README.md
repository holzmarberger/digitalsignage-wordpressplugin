# Marberger Signage

WordPress-Plugin für die Empfangs- und Info-Displays von Holz Marberger (Intranet).
Fachkonzept: `docs/spec/konzept-marberger-signage.md`, Optik: `docs/spec/klickdummy-empfangsdisplay.html`.

## Stand

**Paket 1 – Fundament (0.1.0):** Standorte (Öffnungszeiten, Feiertage AT/BY, Ausnahmen), Schließzeiten,
Display-Kategorien, Displays, Zustands-Endpunkt, Diagnoseansicht, Übersicht mit Online-Status.

Folgt: Paket 2 Empfangsdisplay · Paket 3 Pflege (Display-Redaktion, Handy-Maske) · Paket 4 Info-Display.

## Installation

1. ZIP unter *Plugins → Installieren → Plugin hochladen* einspielen und aktivieren.
2. *Displays → Standorte*: Standort mit Öffnungszeiten anlegen.
3. *Displays → Displays*: Display anlegen (Kennung, Kategorie, Standort, Format).
4. Die angezeigte Adresse (`…/?mm_signage=<kennung>`) in Slide Show als Web-Inhalt eintragen.

Endpunkt: `rest_url('mms/v1/state/<kennung>')` (im Intranet `?rest_route=/mms/v1/state/<kennung>`).
Angemeldete Administratoren können mit `&at=2026-12-24T10:00` einen Zeitpunkt simulieren.

## Entwicklung

- Ziel: PHP 8.1, WordPress 7.1, keine Laufzeitabhängigkeiten.
- Tests der Kernlogik (ohne WordPress): `phpunit` (PHPUnit 9.6+).
- ZIP bauen: `bin/build-zip.sh` → `dist/marberger-signage-<version>.zip`.
