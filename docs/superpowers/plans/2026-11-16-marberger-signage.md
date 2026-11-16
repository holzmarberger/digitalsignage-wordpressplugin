# Marberger Signage – Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** WordPress-Plugin „Marberger Signage“ für Empfangs- und Info-Displays gemäß Fachkonzept v0.3.

**Architecture:** Reine PHP-Kernlogik ohne WP-Abhängigkeit (`includes/Core`: Feiertage, Öffnungskalender, Zeitregeln, Texte, Zustandsauflösung) – vollständig per PHPUnit getestet. Darüber eine WP-Schicht (`includes/WP`: Inhaltstypen, Metaboxen, REST, Player-Seite), die Beiträge in Arrays übersetzt und die Kernlogik aufruft. Player = eine theme-freie Seite mit Vanilla-JS, die den Zustand per REST pollt.

**Tech Stack:** PHP 8.1 (Ziel), WordPress 7.1, Vanilla JS (ES2017, keine Build-Kette), PHPUnit 10 (nur Entwicklung).

**Spec:** `docs/spec/konzept-marberger-signage.md` (v0.3), Optik: `docs/spec/klickdummy-empfangsdisplay.html`

## Global Constraints

- PHP-Syntax muss unter **PHP 8.1** laufen (keine readonly-Klassen, keine DNF-Typen, kein `true`-Typ).
- Keine Laufzeit-Abhängigkeiten (kein Composer-Autoload im Plugin, kein ACF); eigener PSR-4-Autoloader.
- REST nur über `rest_url()` adressieren (Intranet mit `?rest_route=`), Namespace `mms/v1`.
- Intranet über **http**; keine Service Worker, keine externen CDNs; Schriften lokal.
- Prefix `mms_`, Namespace `Marberger\Signage`, Textdomain `marberger-signage`.
- Zeitzone für alle Standorte `Europe/Vienna`.
- Farben: Grün #006729, Hellgrün #e3f0e7, Rot #922224, Text #1f2421, gedämpft #5d6660, Linien #d6d9d7, Warnung #b86e00/#fff3df.

## Review Focus

1. Öffnungszeiten-Eingaben in Freitext („7-12, 13-17“, „07:00–12:00“) → robust parsen, Fehler sichtbar melden statt still verwerfen.
2. Feiertag fällt auf Wochenende / Schließzeit grenzt an Feiertag + Wochenende → ein zusammenhängender Zeitraum, „wieder ab“ korrekt.
3. Display-Kennung unbekannt oder Display ohne Standort/Kategorie → Endpunkt liefert 404 bzw. sinnvolle Standardwerte, kein PHP-Fehler.
4. Standort ohne gepflegte Öffnungszeiten → „Öffnungszeiten auf holz-marberger.at“, kein Endlos-Suchen.
5. Zeitumstellung (letzter Sonntag im März/Oktober) → Blöcke weiterhin korrekt in lokaler Zeit.

---

## Paket 1 – Fundament

### Task 1: Gerüst, Autoloader, Testumgebung
**Files:** `marberger-signage.php`, `includes/Plugin.php`, `composer.json`, `phpunit.xml`, `tests/bootstrap.php`, `bin/build-zip.sh`, `.gitignore`, `README.md`
- [ ] Plugin-Header, Konstanten `MMS_VERSION`, `MMS_FILE`, `MMS_DIR`, `MMS_URL`; Autoloader für `Marberger\Signage\` → `includes/`.
- [ ] PHPUnit 10 als dev-Abhängigkeit; `vendor/bin/phpunit` läuft grün (leere Suite).
- [ ] `bin/build-zip.sh` erzeugt `dist/marberger-signage.zip` ohne tests/docs/vendor.
- [ ] Commit.

### Task 2: Feiertage AT/BY
**Files:** `includes/Core/Holidays.php`, `tests/Core/HolidaysTest.php`
**Interfaces – Produces:** `Holidays::forYear(string $calendar, int $year): array` → `['Y-m-d' => ['key'=>string,'de'=>string,'en'=>string]]`; `Holidays::keys(string $calendar): array` (key => de); `Holidays::easter(int $year): string` ('Y-m-d').
- [ ] Tests: Ostern 2026 = 2026-04-05, 2027 = 2027-03-28; AT 2026 enthält 2026-12-08 (`mariae_empfaengnis`), nicht Karfreitag; BY 2026 enthält 2026-04-03 (`karfreitag`) und 2026-10-03, nicht 2026-10-26.
- [ ] Implementierung (Gauß/anonymer gregorianischer Algorithmus, kein ext-calendar).
- [ ] Tests grün, Commit.

### Task 3: Zeit-Parser und Öffnungskalender
**Files:** `includes/Core/TimeBlocks.php`, `includes/Core/OpeningCalendar.php`, Tests dazu
**Interfaces – Produces:**
- `TimeBlocks::parse(string $input): array{blocks: list<array{0:string,1:string}>, errors: list<string>}` (normalisiert auf `HH:MM`).
- `new OpeningCalendar(array $config, DateTimeZone $tz)` mit `$config = ['weekly'=>[1..7 => list<[from,to]>], 'calendar'=>'AT'|'BY', 'holiday_open'=>list<key>, 'exceptions'=>list<{date,closed,blocks,label,announce_days}>, 'closures'=>list<{id,from,to,label_de,label_en,text_de,text_en,image,announce_days}>, 'min_gap'=>int]`
- `day(DateTimeImmutable $d): array{kind:'open'|'weekly_closed'|'holiday'|'exception'|'closure', blocks: list<[DTI,DTI]>, label_de, label_en, closure:?array}`
- `status(DateTimeImmutable $t): array{state:'open'|'pause'|'after_hours'|'closed_day'|'closure', block_end:?DTI, last_block:bool, next_open:?DTI, reason_de:string, reason_en:string, closure:?array}`
- `nextOpening(DTI $t): ?DTI` (max. 60 Tage), `isOpenDay(DTI $d): bool`, `closingTime(DTI $d): ?DTI`, `warning(DTI $t, int $min): ?array{type:'pause_soon'|'closing_soon', at:DTI, until:?DTI}`, `announcements(DTI $t): list<array>`, `weekTable(): list<array{days_de,days_en,times}>`
- [ ] Tests (Standort Mo–Do 07:00–12:00/13:00–17:00, Fr 07:00–12:00, AT, Schließzeit 2026-12-24..2027-01-06):
  - Di 2026-11-17 10:00 → open; 12:30 → pause, next_open 13:00; 17:30 → after_hours, next_open Mi 07:00.
  - Fr 2026-11-20 11:50 mit 15 min → warning closing_soon 12:00; Di 11:50 → pause_soon until 13:00.
  - 2026-12-08 (Di, Feiertag) → closed_day/holiday; mit holiday_open [mariae_empfaengnis] → open.
  - 2026-12-24 10:00 → closure, next_open 2027-01-07 07:00 (6.1. Feiertag, Wochenende verbunden).
  - Lücke 12:00–12:10 bei min_gap 15 → zusammengeführt, kein pause.
  - Leere Wochenzeiten → next_open null.
  - Ankündigung: 2026-12-10 → Schließzeit angekündigt (14 Tage), 2026-12-09 → nicht.
  - Zeitumstellung 2026-10-25 (So) / 2026-10-26 (Mo, Feiertag) / 2026-10-27 Di 07:00 korrekt.
- [ ] Implementierung, Tests grün, Commit.

### Task 4: Texte und Zeitregeln
**Files:** `includes/Core/Texts.php`, `includes/Core/TimeRule.php`, Tests
**Interfaces – Produces:** `Texts::backAt(DTI $now, ?DTI $next, string $lang): string`; `Texts::warning(array $w, string $lang): string`; `Texts::announcement(array $a, string $lang): string`; `TimeRule::isActive(array $rule, DTI $t, ?OpeningCalendar $cal): bool`; `TimeRule::validate(array $rule): list<string>`.
- [ ] Tests Texte: „Heute ab 13:00 Uhr …“, „Morgen ab 07:00 Uhr …“, „Am Montag ab 07:00 Uhr …“, „Am Donnerstag, 7. Jänner, ab 07:00 Uhr …“; EN-Pendants; Warnungen „Mittagspause von 12:00 bis 13:00 Uhr.“ / „Wir schließen heute um 12:00 Uhr.“
- [ ] Tests Regeln: once mit/ohne daily; weekly über Mitternacht (Fr 22:00–02:00 aktiv Sa 01:00); `to:'close'` nutzt closingTime, an geschlossenen Tagen inaktiv; `open_days_only`.
- [ ] Implementierung, Tests grün, Commit.

### Task 5: Zustandsauflösung
**Files:** `includes/Core/StateResolver.php`, `includes/Core/Settings.php`, Tests
**Interfaces – Produces:** `Settings::defaults(string $type): array`, `Settings::merge(array $category, array $overrides): array`; `new StateResolver(OpeningCalendar $cal, array $settings)`, `resolve(DTI $t, array $content = []): array{rank, closed:?array, fullpage:?array, greeting:?array, banners:list, below:?array}` – Ränge `fullpage|greeting|closure|closed|standard`, Balken max. 3 in Reihenfolge pause/warning → notices (Priorität, Änderung) → announcements.
- [ ] Tests: Feierabend + Empfang → closed; Feierabend + Info → standard; Pause + Empfang → standard + Balken pause; Gruß um 19:00 → greeting; Vollbild mit open_days_only am Feiertag → nicht aktiv; 4 Balken → 3; `below` einer Vollbild-Seite = Zustand der nächsten Rangstufe.
- [ ] Implementierung, Tests grün, Commit.

### Task 6: WP-Schicht – Inhaltstypen, Metaboxen, Einstellungen
**Files:** `includes/WP/PostTypes.php`, `includes/WP/Store.php`, `includes/WP/Admin/{Menu,LocationBox,ClosureBox,CategoryBox,DisplayBox,SettingsPage}.php`, `assets/admin/admin.{css,js}`
- [ ] CPTs `mms_location`, `mms_closure`, `mms_category`, `mms_display` unter Menü „Displays“; Daten in Meta `_mms` (Array).
- [ ] Standort: Logo, Koordinaten, Kalender, Feiertage „trotzdem geöffnet“, Wochenzeiten als Textfelder (TimeBlocks), Ausnahmen-Wiederholgruppe; Fehler als Admin-Hinweis.
- [ ] Schließzeit, Kategorie (alle Felder Konzept 9.2), Display (Kategorie, Standort, Format, Abweichungen, URLs, Lebenszeichen, Zustandsvorschau mit Datum/Uhrzeit).
- [ ] `Store::displayContext(string $slug): ?array` liefert Kalender-Config, gemergte Settings, Standort-/Display-Daten.
- [ ] Commit.

### Task 7: REST-Endpunkt, Lebenszeichen, Diagnose-Player
**Files:** `includes/WP/Rest.php`, `includes/WP/Player.php`, `assets/player/diag.js`
- [ ] `GET mms/v1/state/<kennung>`: 404 bei unbekannter Kennung, `nocache_headers()`, Parameter `at` nur für `manage_options`; Lebenszeichen höchstens alle 30 s schreiben.
- [ ] `?mm_signage=<kennung>` liefert theme-freie Diagnoseseite (Zustand groß, Pollen) – wird in Paket 2 durch den echten Player ersetzt.
- [ ] Integrationstest in lokaler WP-Installation (SQLite), Screenshots; ZIP bauen; Commit.

## Paket 2 – Empfangsdisplay (Folgeplan)
CPTs `mms_node`, `mms_greeting`, `mms_notice`, `mms_fullpage`; Player (Kopf, Wegweiser mit Layout-Regeln 1–8, Detailseite mit Bewegtbild, Gruß-Karte, Balken, Geschlossen/Schließzeit/Vollbild, Besucher-Knopf, Rücksprung, DE/EN) nach Klickdummy.

## Paket 3 – Pflege (Folgeplan)
Häkchen Display-Redaktion (Capabilities), mobile Pflegemaske (Gruß-Passwort verschlüsselt/abrufbar, WP-Login), Bild-Umwandlung (Imagick, HEIC), Vorlagen, Platzhalter-Sperre, Cron-Aufräumen.

## Paket 4 – Info-Display (Folgeplan)
Abfolge (Web-Seite skaliert, News-Folie, Display-Seite `mms_page`), News `mms_news`, Laufband, Wetter (Open-Meteo-Cache), Vorladen nur nächstes Board.
