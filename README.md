# Marberger Signage

WordPress-Plugin für die Empfangs- und Info-Displays von Holz Marberger (Intranet).
Fachkonzept: `docs/spec/konzept-marberger-signage.md`, Optik: `docs/spec/klickdummy-empfangsdisplay.html`,
Entwicklerdokumentation: `docs/entwicklung.md`. Die Bedienung beschreibt das Anwenderhandbuch (PDF, separat).

## Änderungen

**1.0.1 (16.11.2026)**
- Erklärtexte direkt im Backend: Kasten über jeder Liste (z. B. was Vollbild-Seiten tun), kompakt in jeder Maske.
- Einheitliche Begriffe: Menüpunkt „Wegweiser-Kacheln“ (statt „Wegweiser“), Kasten „Einordnung“ mit
  „Übergeordnete Kachel“ und „Reihenfolge“, „Grüße“ auch in der Handy-Maske.
- Listen mit aussagekräftigen Spalten und Status (läuft jetzt, heute später, geplant, abgelaufen, unvollständig,
  pausiert); Display-Seiten zeigen, in welcher Abfolge sie verwendet werden.
- „Vorschau“-Knöpfe je Display in den Masken (öffnen die Anzeige zum Beginn des Eintrags).
- Display- und Kategorie-Masken blenden nur die Felder des jeweiligen Typs (Empfang/Info) ein.
- Vorankündigungen im Modus „Laufband + News-Folie“ erscheinen auch auf der News-Folie.
- Zeitsimulation (`at`) auch für die Display-Redaktion; Mindestdauer 5 s je Abfolge-Eintrag;
  Grüße nur noch Empfangsdisplays zuordenbar; Display-Redaktion kann Einträge direkt neu anlegen.
- Player: Texte in Kacheln und Gruß-Karte werden bei Überlänge automatisch verkleinert.

**1.0.0** – Erstausgabe (Pakete 1–4).

## Umfang

- **Struktur (Administratoren):** Standorte (Öffnungszeiten, Feiertage AT/BY, Ausnahmen), Schließzeiten,
  Display-Kategorien mit Standardwerten und Standard-Abfolge, Displays (Kennung, Format, Abweichungen,
  Wegweiser-Startknoten, eigene Abfolge), Einstellungen (Lücken, Gruß-Passwort, Schnellvorlagen).
- **Redaktion (Häkchen „Display-Redaktion“ im Benutzerprofil):** Grüße, Hinweise, Wegweiser-Kacheln,
  Vollbild-Seiten, News, Display-Seiten.
- **Empfangsdisplay:** Kopf mit Logo/Datum/Wetter/Uhr, Wegweiser (bis 8 Kacheln je Ebene, Untermenüs,
  Detailseite mit Bewegtbild, Wegschritten, Ansprechpartner, QR-Code), Gruß, Balken (Pause, Vorwarnung,
  Hinweise, Vorankündigung), Vollbild-Seiten mit Besucher-Knopf, Geschlossen- und Schließzeit-Seite,
  DE/EN, Rücksprung mit Countdown-Ring.
- **Info-Display:** Abfolge aus Web-Seiten (Grafana, verkleinert auf eine Bildschirmhöhe), Display-Seiten
  und News-Folie; Zeitfenster je Eintrag; Laufband; Hinweise; Vollbild-Seiten.
- **Handy-Pflegemaske** `…/?mm_pflege=1`: Gruß-Passwort → nur Grüße; WordPress-Konto mit Display-Redaktion
  → Grüße, Hinweise, News. Fotos vom Handy (auch HEIC, sofern Imagick HEIC lesen kann) werden verkleinert.
- **Aufräumen:** Grüße samt hochgeladenem Logo werden 30 Tage nach Ablauf gelöscht (täglicher WP-Cron).

## Installation

1. ZIP unter *Plugins → Installieren → Plugin hochladen* einspielen und aktivieren.
2. *Displays → Standorte*: Standort mit Öffnungszeiten, Koordinaten und Logo anlegen.
3. *Displays → Displays*: Display anlegen (Kennung, Kategorie, Standort, Format).
4. Die angezeigte Adresse (`…/?mm_signage=<kennung>`) in Slide Show als Web-Inhalt eintragen.
5. *Displays → Einstellungen*: Gruß-Passwort setzen; Mitarbeitern im Benutzerprofil „Display-Redaktion“ geben.

Endpunkt: `rest_url('mms/v1/state/<kennung>')`. Angemeldete Administratoren und die Display-Redaktion können im Player und im
Endpunkt mit `&at=2026-12-24T10:00` einen Zeitpunkt simulieren; `&diag=1` zeigt die Diagnoseansicht.

## Hinweise für den Betrieb

- WP-Cron muss laufen (Standard: bei Seitenaufrufen; bei `DISABLE_WP_CRON` einen System-Cron einrichten).
- Wetter kommt serverseitig von Open-Meteo (30 min Zwischenspeicher); ohne Internetzugang des Servers
  bleibt das Wettersymbol einfach weg.
- Grafana: `allow_embedding = true`, Kiosk-Parameter in der URL.
- HEIC prüfen: `convert -list format | grep -i heic` am Server.

## Entwicklung

- Ziel: PHP 8.1, WordPress ≥ 6.5 (getestet mit 7.x), keine Laufzeitabhängigkeiten. Details: `docs/entwicklung.md`.
- Tests der Kernlogik (ohne WordPress): `phpunit` (PHPUnit 9.6+).
- ZIP bauen: `bin/build-zip.sh` → `dist/marberger-signage-<version>.zip`.
- Fremdbestandteile: Poppins (SIL OFL, `assets/fonts/OFL-Poppins.txt`), qrcode-generator 2.0.4 (MIT).
