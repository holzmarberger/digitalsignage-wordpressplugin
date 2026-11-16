# Marberger Signage – Entwicklerdokumentation

Stand: Version 1.0.1 · 16.11.2026

Diese Dokumentation richtet sich an Entwickler, die das Plugin warten oder erweitern. Die Bedienung beschreibt
das Anwenderhandbuch (PDF; Quellen und Erzeugung in `docs/handbuch/`); das Fachkonzept liegt in
`docs/spec/konzept-marberger-signage.md`.

---

## 1. Überblick

Das Plugin liefert zwei Display-Arten aus einem WordPress (Intranet):

| Display-Art | Gerät | Bedienung | Inhalt |
|---|---|---|---|
| Empfang (`reception`) | 43″ Hochformat (1080 × 1920) oder Querformat | Touch | Kopf, Wegweiser, Gruß, Balken, Vollbild-, Geschlossen-, Schließzeit-Seite |
| Info (`info`) | Querformat (1920 × 1080) | keine | Kopf, Abfolge (URL/Grafana, Display-Seite, News-Folie), Laufband, Balken |

Die Geräte (Android, App *Slide Show*) zeigen nur `…/?mm_signage=<kennung>`. Die gesamte Logik läuft auf dem
Server; der Player fragt den Zustand per REST ab und rendert ihn.

```
Slide Show (WebView)
  └─ GET /?mm_signage=<key>            Player.php  → HTML-Hülle + assets/player/player.js
       └─ GET mms/v1/state/<key>       Rest.php    → Store::displayContext()
                                                    → Core\StateResolver::resolve()
                                                    → Store::directory() | Sequence::resolve()
Handy
  └─ GET /?mm_pflege=1                 Mask.php    → assets/manage/manage.js
       └─ mms/v1/manage/*              ManageRest.php (Gruß-Passwort-Token oder WP-Login)
Backend
  └─ wp-admin, Menü „Displays“         WP\Admin\* (Metaboxen, Spalten, Hilfe, Übersicht, Einstellungen)
```

## 2. Schichten und Verzeichnisse

```
marberger-signage.php        Plugin-Header, Konstanten (MMS_VERSION …), Autoloader, Hooks (activate/deactivate)
uninstall.php                entfernt Optionen und Cron – Inhalte bleiben bewusst erhalten
includes/Plugin.php          boot(), activate(), Standardoptionen, Standard-Kategorien „Empfang“/„Info intern“
includes/Core/               reine PHP-Logik ohne WordPress-Abhängigkeit (Unit-getestet)
  Holidays.php               Feiertage AT / BY (fix + beweglich, Ostern nach Gauß)
  TimeBlocks.php             Parser für Öffnungszeiten „07:00–12:00, 13:00–17:00“, Kurzform „7-12, 13-17“
  OpeningCalendar.php        Tagesplan, Status (open/pause/after_hours/closed_day/closure), nächste Öffnung,
                             Vorwarnung, Vorankündigungen, Wochentabelle
  TimeRule.php               einheitliche Zeitregeln (once/weekly) für Grüße, Hinweise, Vollbild-Seiten, Abfolge
  Settings.php               Felder, Standardwerte je Typ, Bereinigung, Merge Kategorie ← Display-Abweichungen
  StateResolver.php          Rangfolge + Balken → Zustand eines Displays zu einem Zeitpunkt
  Texts.php                  alle automatisch erzeugten Sätze DE/EN („… sind wir wieder für Sie da“)
includes/WP/                 WordPress-Anbindung
  PostTypes.php              Inhaltstypen, Rechte-Mapping, Meta-Zugriff (data()/save())
  Store.php                  Datenzugriff für den Endpunkt (Kontext, Inhalte, Wegweiser-Baum, News, Abfolge)
  Rest.php                   GET mms/v1/state/<key>
  ManageRest.php             REST der Handy-Pflegemaske
  Access.php                 Gruß-Passwort (verschlüsselt) und signierte Token
  Caps.php                   Recht mms_edit über Benutzer-Meta „Display-Redaktion“
  Editorial.php              Bereinigung + Validierung der redaktionellen Inhalte (Backend und Maske gemeinsam)
  Status.php                 Status-Chips (läuft jetzt, geplant, unvollständig …) für Listen und Maske
  Sequence.php               Abfolge-Einträge bereinigen und für den Player auflösen
  Player.php / Mask.php      Ausgabe der Player- und Masken-Seite über Query-Variablen
  Images.php                 Upload vom Handy (inkl. HEIC → JPG), Verkleinern auf 1600 px
  Weather.php                Open-Meteo, 30 min Transient
  Templates.php              Schnellvorlagen für Grüße
  Cleanup.php                täglicher Cron: abgelaufene Grüße + hochgeladene Logos nach 30 Tagen löschen
  Admin/                     Menü, Metaboxen (Box-Basisklasse), Abfolge-Editor, Spalten, Hilfetexte, Übersicht
assets/player/               player.js/.css (ES5, Android-WebView), diag.js/.css (Diagnoseansicht)
assets/manage/               manage.js/.css (Handy-Pflegemaske)
assets/admin/                admin.js/.css (Repeater, Bereichsumschaltung Empfang/Info, Hilfekästen, Chips)
assets/vendor/qrcode.js      qrcode-generator 2.0.4 (MIT)
assets/fonts/                Poppins (SIL OFL)
tests/Core/                  PHPUnit-Tests der Core-Schicht
bin/build-zip.sh             Release-ZIP
docs/handbuch/               Anwenderhandbuch: HTML-Quellen, Screenshots, Werkzeuge zum Neu-Erzeugen
```

Namensraum `Marberger\Signage`, Präfix `mms_` für Typen, Optionen, Hooks und Metas. Ziel: PHP 8.1,
WordPress ≥ 6.5 (getestet mit 7.x), keine Composer-Laufzeitabhängigkeiten.

## 3. Datenmodell

### 3.1 Inhaltstypen

Alle Typen sind nicht öffentlich (`public => false`, kein Rewrite, nicht in der WP-REST-API) und hängen unter
dem Menü `mms`. Fachdaten liegen **als ein Array im Meta-Feld `_mms`** (`PostTypes::META`); Zugriff nur über
`PostTypes::data($id)` und `PostTypes::save($id, $data)`.

| Konstante | Slug | Recht | `post_title` | Editor | Besonderheit |
|---|---|---|---|---|---|
| `GREETING` | `mms_greeting` | `mms_edit` | Name / Gruppe | – | nur Empfangsdisplays |
| `NOTICE` | `mms_notice` | `mms_edit` | Text Deutsch | – | |
| `NODE` | `mms_node` | `mms_edit` | Titel Deutsch | – | hierarchisch, `menu_order` = Reihenfolge |
| `FULLPAGE` | `mms_fullpage` | `mms_edit` | Überschrift | ja | |
| `NEWS` | `mms_news` | `mms_edit` | Titel | – | |
| `PAGE` | `mms_page` | `mms_edit` | Titel (Kopfzeile) | ja | nur über eine Abfolge sichtbar |
| `DISPLAY` | `mms_display` | `manage_options` | Name | – | Kennung in `_mms_key` |
| `CATEGORY` | `mms_category` | `manage_options` | Name | – | Typ + Standardwerte + Standard-Abfolge |
| `LOCATION` | `mms_location` | `manage_options` | Name | – | Öffnungszeiten, Feiertage, Ausnahmen |
| `CLOSURE` | `mms_closure` | `manage_options` | interne Bezeichnung | – | mehrtägige Schließung |

`map_meta_cap => false`: alle Post-Capabilities eines Typs werden auf ein einziges Recht gemappt
(`PostTypes::caps()`). Für Nicht-Administratoren mit `mms_edit` existieren versteckte Untermenüeinträge
`post-new.php?post_type=…`, sonst verweigert WordPress den Zugriff auf „Neu anlegen“ (Menu.php).

### 3.2 Inhalt von `_mms` je Typ

Redaktionelle Typen tragen zusätzlich `valid` (bool) – gesetzt beim Speichern, wenn `Editorial::*()` keine
Fehler liefert. Nur gültige Einträge werden ausgeliefert (`Store::forDisplay()`, `Store::news()`); im Backend
erscheinen ungültige als „unvollständig“.

| Typ | Schlüssel |
|---|---|
| Gruß | `kicker_de` (Std. „Herzlich willkommen“, ≤ 60), `kicker_en`, `sub_de`/`sub_en` (≤ 140), `logo` (Attachment-ID), `from`/`to` (`Y-m-d H:i`), `daily` (bool), `displays` (IDs, nur Empfang) |
| Hinweis | `text_en` (≤ 160), `icon` (`right` `left` `up` `info` `warn`), `days` (1–7), `from` (`H:i`), `to` (`H:i` oder `close`), `valid_from`/`valid_to` (`Y-m-d`), `displays`, `active`, `priority` (0–99) |
| Wegweiser-Kachel | `title_en` (≤ 60), `sub_de`/`sub_en` (≤ 90), `icon` (siehe `Editorial::ICONS`), `media`, `steps_de`/`steps_en` (je ≤ 8 Zeilen), `contact`, `ext`, `photo`, `qr_url` |
| Vollbild-Seite | `rule_type` (`weekly`/`once`), `days`, `from`, `to`, `once_from`/`once_to`, `open_days_only`, `visitor_button`, `displays`, `priority` |
| News | `text` (≤ 600), `image`, `from`/`to` (`Y-m-d`), `sites` (Standort-IDs), `slide`, `ticker` |
| Display-Seite | `format` (`quer`/`hoch`), `valid` (immer true) |
| Display | `category`, `location` (0 = ohne Standort → immer geöffnet), `format` (`hoch`/`quer`), `overrides` (nur abweichende Settings), `start_node`, `sequence_own` (bool), `sequence` |
| Display-Kategorie | alle Felder aus `Settings::fields()` + `type` (`reception`/`info`) + `sequence` |
| Standort | `logo`, `lat`, `lon`, `calendar` (`AT`/`BY`), `weekly` (1–7 → Blöcke `[[from,to],…]`), `weekly_raw`, `holiday_open` (Feiertagsschlüssel), `exceptions` (`date`, `kind`, `blocks`, `label`, `announce_days`) |
| Schließzeit | `label_de`/`label_en`, `text_de`/`text_en`, `image`, `from`/`to` (`Y-m-d`), `locations`, `announce_days` (0–90, Std. 14) |

Abfolge-Eintrag (`Sequence::sanitize()`): `{type: url|news|page, title, url, page, duration, days, from, to, width, height}`.
`duration` 0 = Standarddauer der Kategorie, mindestens 5 s (`Sequence::resolve()`). `width`/`height` =
Design-Auflösung einer Web-Seite (Std. 1920 × 1080); der Player skaliert den iFrame auf die Inhaltsfläche.

### 3.3 Weitere Speicherorte

| Art | Schlüssel | Inhalt |
|---|---|---|
| Post-Meta | `_mms_key` | Kennung eines Displays (`[a-z0-9_-]`, eindeutig) |
| Post-Meta | `_mms_last_seen` | Unix-Zeit der letzten Abfrage (höchstens alle 30 s geschrieben) |
| Post-Meta (Attachment) | `_mms_upload` | Markiert Logos aus der Handy-Maske → werden mit dem Gruß gelöscht |
| User-Meta | `mms_redaktion` | `'1'` = Display-Redaktion |
| Option | `mms_settings` | `min_gap` (Minuten, Lücken in Öffnungszeiten ignorieren), Std. 15 |
| Option | `mms_templates` | Schnellvorlagen (Grüße) |
| Option | `mms_greet_pw` | Gruß-Passwort, verschlüsselt (`o:` AES-256-CBC, `s:` libsodium, `p:` Klartext-Fallback) |
| Option | `mms_greet_ver` | Zähler; jede Passwortänderung macht alle Token ungültig |
| Transient | `mms_wx_<md5>` | Wetter je Koordinate (30 min, bei Fehler 10 min) |
| Transient | `mms_login_<md5(ip)>` | Fehlversuche Masken-Login (Sperre ab 10 für 10 min) |
| Transient | `mms_errors_<user>` | Validierungsfehler nach dem Speichern einer Metabox (120 s) |
| Cron | `mms_daily_cleanup` | täglich, `Cleanup::run()` |

**Achtung:** Der Schlüssel für das Gruß-Passwort leitet sich aus `wp_salt('auth')` ab. Werden die Salts in
`wp-config.php` erneuert, ist das gespeicherte Passwort nicht mehr lesbar und alle Token ungültig – das
Passwort unter *Displays → Einstellungen* neu setzen.

## 4. Kernlogik (Core)

### 4.1 Zeitregeln

```
once:   {type:'once', from:'Y-m-d H:i', to:'Y-m-d H:i', daily?:bool}
        daily = mehrtägig, aber jeden Tag nur im Fenster time(from)–time(to)
weekly: {type:'weekly', days:[1..7], from:'H:i', to:'H:i'|'close',
         valid_from?:'Y-m-d', valid_to?:'Y-m-d', open_days_only?:bool}
        to < from → über Mitternacht; 'close' → bis Geschäftsschluss (braucht OpeningCalendar)
```

`TimeRule::isActive($rule, $t, $cal)` und `TimeRule::validate($rule)` (liefert deutsche Fehlermeldungen).
Abfolge-Einträge ohne Tage und Zeiten gelten immer (`Sequence::rule()` → `null`).

### 4.2 Öffnungskalender

`new OpeningCalendar($config, $tz)` mit `Store::calendarConfig($locationId)`:
`weekly`, `calendar`, `holiday_open`, `exceptions`, `closures` (inkl. Texte/Bild), `min_gap`.
Ohne Standort: `Store::alwaysOpenConfig()` (00:00–24:00, keine Feiertage, keine Vorwarnung).

`status($t)['state']`: `open` · `pause` (Lücke zwischen Blöcken desselben Tages ≥ `min_gap`) · `after_hours`
· `closed_day` · `closure`. Angrenzende geschlossene Tage werden zu einer Schließung zusammengezogen;
`nextOpening()` sucht bis zu 60 Tage voraus (`SEARCH_DAYS`).

### 4.3 Zustandsauflösung

`StateResolver::resolve($t, $content)` mit `$content = Store::content($ctx)` (filterbar, siehe 8.):

1. **Rang** (genau eine Hauptanzeige): `fullpage` > `greeting` (nur Empfang) > `closure` > `closed` > `standard`.
   `closure`/`closed` nur, wenn der Modus der Kategorie `fullscreen` ist. Bei `fullpage` enthält `below` den
   Rang darunter – der Player zeigt ihn nach „Besucher? Hier tippen“.
2. **Balken** (`banners`, max. `MAX_BANNERS = 3`): automatische (Pause/Geschlossen im Modus `banner`,
   Vorwarnung) → Hinweise nach `priority` desc, `modified` desc → Vorankündigungen (`announce_mode = banner`).
3. **Laufband-Ankündigungen** (`ticker`), wenn `announce_mode = ticker` (Standard bei Info).

Rückgabe: `rank`, `below`, `status`, `closed` (`kind`, `next_open`, `back_de/en`, `reason_de/en`, `closure`),
`fullpage`, `greeting`, `banners[] {type, icon, text_de, text_en, id}`, `ticker[] {text_de, text_en}`.

### 4.4 Einstellungen

`Settings::fields()` definiert alle Kategorie-Felder mit `scope` (`both`/`reception`/`info`); das Backend blendet
Felder per `data-scope` je nach Kategorietyp ein. `Settings::defaults($type)` liefert die Standardwerte
(Empfang: Pause = Balken, Feierabend/Schließzeit = Vollbild, Vorwarnung 15 min, Vorankündigung = Balken;
Info: alles „nichts“, Vorankündigung = Laufband, News an, 30 s je Eintrag; beide: Abfrage 60 s, Rücksprung 45 s).
`Settings::merge($category, $overrides)` ergibt die wirksamen Werte eines Displays.

## 5. REST-Schnittstellen

Namensraum `mms/v1`. Immer über `rest_url()` adressieren – im Intranet ohne Pretty-Permalinks lautet die Form
`/?rest_route=/mms/v1/…`.

### 5.1 `GET state/<key>`

Öffentlich (Displays haben keinen Login). Antwort `Cache-Control: no-store`.

| Parameter | Wirkung |
|---|---|
| `at` | Simulierter Zeitpunkt (`2026-12-24T10:00`), nur mit Recht `mms_edit` (WP-Cookie + `X-WP-Nonce`). Ohne Recht wird `at` ignoriert. Simulierte Abrufe aktualisieren `_mms_last_seen` nicht. |

Antwort (gekürzt):

```json
{
  "display":  {"key":"empfang-oetztal","name":"…","format":"hoch","type":"reception"},
  "settings": {"poll":60,"idle":45,"pause_mode":"banner", "…": "…"},
  "location": {"id":12,"name":"Ötztal Bahnhof","logo":"…","lat":47.23,"lon":10.86},
  "weather":  {"temp":3,"icon":"sun"},
  "opening":  {"week":[{"days_de":"Montag – Freitag","days_en":"…","times":"07:00–16:30"}]},
  "state":    { "…": "siehe 4.3" },
  "directory":[{"id":1,"title_de":"…","children":[…]}],          // nur Empfang
  "sequence": [{"id":"e0","type":"url","duration":30,"url":"…"}], // nur Info
  "news_ticker":["…"],                                            // nur Info
  "hash":"…","version":"1.0.1","server_time":"2026-11-16T10:00:00+01:00","simulated":false
}
```

- `directory`: Kinder von `start_node` (0 = oberste Ebene), max. 8 je Ebene, Tiefe 0–4, sortiert nach
  `menu_order`, Titel. Blätter tragen Detaildaten (`media {url,type}`, `steps_*`, `contact`, `ext`, `photo`, `qr_url`).
  Englische Felder fallen auf Deutsch zurück.
- `sequence`: nur gerade gültige Einträge. News-Folie entfällt, wenn keine News als Folie gültig ist.
  Vorankündigungen im Modus „Laufband“ werden zusätzlich als News mit `kicker: "Ankündigung"` vorangestellt.
- `hash`: MD5 über den Inhalt (vor `version`, `server_time`, `simulated`) – der Player rendert nur bei Änderung neu.
- 404 `mms_unknown_display` bei unbekannter oder nicht veröffentlichter Kennung, 400 `mms_bad_time`.

### 5.2 `manage/*` (Handy-Pflegemaske)

| Route | Methode | Recht |
|---|---|---|
| `manage/login` | POST `{password}` → `{token, days}` | offen, 10 Fehlversuche / 10 min je IP |
| `manage/bootstrap` | GET – Standorte, Displays, Vorlagen, Rolle | Gruß |
| `manage/upload` | POST multipart `file` → `{id, url}` | Gruß |
| `manage/greetings[/<id>]` | GET Liste, POST anlegen/ändern, DELETE | Gruß |
| `manage/notices[/<id>]` | wie oben | Redaktion |
| `manage/news[/<id>]` | wie oben | Redaktion |

„Gruß“ = `mms_edit` **oder** gültiges Token im Kopf `X-MMS-Token`. „Redaktion“ = `mms_edit` (WP-Login, Nonce im
Kopf `X-WP-Nonce`). Token: `base64url(json{exp,v}) . base64url(hmac_sha256)`, 30 Tage gültig, ungültig bei
Passwortänderung (`v`) oder leerem Passwort. Validierung über dieselben `Editorial::*()`-Funktionen wie im
Backend; Fehler kommen als `400` mit `data.errors[]`.

## 6. Frontend

### 6.1 Player (`assets/player/player.js`)

- ES5, keine Abhängigkeiten außer `qrcode.js`; läuft in der Android-WebView. Konfiguration als JSON in
  `#mms-config` (`stateUrl`, `key`, `at`, `nonce`, `logo`, `version`).
- Bühne mit fester Fläche 1080 × 1920 bzw. 1920 × 1080, per `transform: scale()` eingepasst (`fit()`).
- `poll()`: Abfrage im Intervall `settings.poll` (min. 15 s), Uhrversatz aus `server_time` (`S.offset`).
  Bei Fehlern Klasse `offline` (roter Punkt), neuer Versuch nach 30 s; letzte Anzeige bleibt stehen.
- Empfang: Neu-Rendern nur im Ruhezustand (Startseite, Deutsch); sonst `S.pending` bis zum Rücksprung.
  Rücksprung nach `settings.idle` s, Countdown-Ring in den letzten 10 s.
- Info: `softInfo()` aktualisiert Laufband, Balken und Kopf ohne die laufende Folie neu zu laden; die Abfolge
  wird nur neu aufgebaut, wenn sich ihre Signatur (`seqSig()`) ändert.
- Automatische Schriftverkleinerung bei Überlauf: `fitTiles()`, `fitGuest()`.
- Nächtliches Neuladen um 03:00 (nur wenn der Server erreichbar ist, nicht bei `at`).
- `?diag=1` liefert statt des Players die Diagnoseansicht (`diag.js`).

### 6.2 Handy-Pflegemaske (`assets/manage/manage.js`)

Single-Page-App ohne Framework. Token im `localStorage` (`try/catch`), WP-Login über `wp_login_url()` mit
Rücksprung. Reiter „Grüße“ immer, „Hinweise“/„News“ nur mit `editor: true`. Platzhalter in eckigen Klammern
blockieren das Speichern (gleiche Prüfung serverseitig: `Editorial::hasPlaceholder()`).

### 6.3 Backend

- `Admin\Box` (abstrakt): Metabox, Nonce `mms_save_<id>`, Rechteprüfung über `PostTypes::capFor()`,
  Fehler per Transient → `admin_notices`. Konkrete Boxen: `LocationBox`, `ClosureBox`, `CategoryBox`,
  `DisplayBox`, `EditorialBoxes` (Gruß, Hinweis, Kachel, Vollbild-Seite, News, Display-Seite).
- `Admin\Help`: Erklärkasten je Typ über `views_edit-<type>` (Liste) und kompakt `edit_form_after_title` (Maske).
- `Admin\Columns`: Listenspalten inkl. Status-Chip (`WP\Status`), „Verwendet in Abfolge“ bei Display-Seiten.
- `EditorialBoxes::previewRow()`: „Vorschau“-Knöpfe je Display mit `&at=` (Beginn des Eintrags).
- `Admin\Overview`: Schnellzugriff, Handy-Maske-Adresse, Gruß-Passwort, Displays mit Online-Status
  (grün = Abfrage in den letzten 5 Minuten).
- Einstellungsseite über `admin-post.php?action=mms_settings`.

## 7. Rechte

| Recht | Wer | Darf |
|---|---|---|
| `manage_options` | Administratoren | alles |
| `mms_edit` | Administratoren + Benutzer mit `mms_redaktion = '1'` | redaktionelle Typen, Handy-Maske voll, `at`-Simulation |
| Token | Empfang mit Gruß-Passwort | nur Grüße über `manage/*` |

`Caps::grant()` (Filter `user_has_cap`) vergibt für Redaktion zusätzlich `upload_files` und `read`.

## 8. Erweiterungspunkte

| Hook | Typ | Zweck |
|---|---|---|
| `mms_state_content` | Filter `($content, $ctx)` | Grüße/Hinweise/Vollbild-Seiten vor der Auflösung ergänzen oder filtern (z. B. Termine aus einem externen Kalender als Gruß einspielen) |

Weitere Inhaltstypen folgen dem Muster: Konstante + Eintrag in `PostTypes::definitions()`, Bereinigung in
`Editorial`, Box in `Admin\EditorialBoxes`, Auslieferung in `Store`, Darstellung im Player, Hilfetext in
`Admin\Help::texts()`, Spalten in `Admin\Columns::COLS`, Status in `WP\Status::of()`.

## 9. Tests und Qualitätssicherung

```bash
phpunit                                  # Core-Tests (Holidays, OpeningCalendar, TimeRule, StateResolver, Texts)
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

Die WP-Schicht hat keine automatisierten Unit-Tests. Einen durchgehenden Oberflächen-Durchlauf liefert die
Handbuch-Erzeugung (`docs/handbuch/werkzeuge/erzeugen.sh alles`): Sie legt alle Inhaltstypen über das Backend an,
öffnet Player und Handy-Maske zu vielen Zeitpunkten und meldet JavaScript-Fehler. Zusätzlich manuell:

1. Testinstanz (z. B. WordPress + SQLite-Integration, `php -S`), Plugin aktivieren, `WP_DEBUG_LOG` an.
2. Je ein Empfangs- und Info-Display anlegen; über *Zustand testen* bzw. `&at=` prüfen: Werktag, Pause,
   Vorwarnung, Feierabend, Wochenende, Feiertag, Feiertag trotzdem geöffnet, Schließzeit mit Vorankündigung.
3. Gruß mit Logo, Hinweis „bis Geschäftsschluss“, Vollbild-Seite mit Besucher-Knopf, Wegweiser mit
   Unterauswahl und Detailseite (Video, QR) – jeweils DE/EN.
4. Handy-Maske: Login mit Gruß-Passwort, Gruß mit HEIC-Foto, Passwortwechsel → alter Token abgelehnt;
   Login als Redaktion → Hinweise/News.
5. `debug.log` leer.

## 10. Build und Release

1. Version an zwei Stellen erhöhen: Plugin-Header `Version:` und `MMS_VERSION` in `marberger-signage.php`.
   Player- und Masken-Assets werden mit `?v=<version>` ausgeliefert – ohne Versionssprung behalten die
   WebViews alte Dateien im Cache.
2. `README.md` (Abschnitt Änderungen) nachführen; bei fachlichen Änderungen auch das Konzept.
   Handbuch: Texte in `docs/handbuch/src/` anpassen; bei sichtbaren Änderungen Screenshots neu erzeugen
   (`docs/handbuch/werkzeuge/erzeugen.sh alles`, siehe `docs/handbuch/README.md`).
3. Tests und Lint (Abschnitt 9).
4. `bin/build-zip.sh` → `dist/marberger-signage-<version>.zip` (ohne `tests/`, `docs/`, `bin/`).
5. Einspielen über *Plugins → Plugin hochladen*; WordPress ersetzt die vorhandene Version, Inhalte bleiben.

## 11. Bekannte Grenzen

- Max. 8 Kacheln je Ebene und 3 Balken gleichzeitig (Platz am 43″-Display).
- Feiertagskalender nur AT und BY; Südtirol oder weitere Länder → `Holidays::FIXED`/`MOVABLE` erweitern.
- Zeitzone fest `Europe/Vienna` (Server: `Store::timezone()`, Player: `TZ`).
- Wetter braucht ausgehendes HTTPS vom Server zu `api.open-meteo.com`.
- HEIC-Upload braucht Imagick mit HEIC-Unterstützung (`convert -list format | grep -i heic`).
- Grafana im iFrame braucht `allow_embedding = true`; Anmeldung im iFrame ist nicht vorgesehen
  (anonymer Zugriff oder öffentliches Dashboard).
