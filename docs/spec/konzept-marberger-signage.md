# Konzept „Marberger Signage“ – Empfangs- und Info-Displays

| | |
|---|---|
| **Unternehmen** | Holz Marberger GmbH |
| **Dokument** | Fachkonzept Display-System (WordPress-Plugin) |
| **Version / Stand** | 0.4 / 16.11.2026 (Plugin 1.0.0 umgesetzt) |
| **Status** | Entwurf zur Freigabe |
| **Klickdummy** | `klickdummy-empfangsdisplay.html` (Parameter siehe Anhang A) |

---

## 1. Zweck und Hintergrund

Am Haupteingang Ötztal Bahnhof wurde am 16.11.2026 ein Touch-Kundenstopper (TecMaschin DKST-LED43) in Betrieb genommen. Er soll Besucher begrüßen, ihnen den Weg zeigen und das Telefon neben dem Display mittelfristig ersetzen. Daneben gibt es Info-Displays ohne Touch (z. B. Mannschaftsraum), auf denen derzeit Grafana-Boards über Slide Show laufen.

Ziel ist **ein** System im Intranet-WordPress, über das alle Displays aller Standorte an einer Stelle gepflegt werden – Empfangs- und Info-Displays, Hoch- und Querformat.

## 2. Ziele, Nicht-Ziele, Anti-Ziele

**Ziele**
- Besucher finden am Empfangsdisplay ohne Hilfe ihr Ziel (Verkauf konstruktiv, Verkauf dekorativ, Verwaltung, Paketablieferung mit Unterschrift).
- Ein persönlicher Gruß (z. B. für eine Schulklasse) ist in unter einer Minute vom Handy eingetragen – mit einfachem Passwort.
- Zeitgesteuerte Inhalte (Team-Info am Morgen, Hinweis Freitagnachmittag, Öffnungszeiten am Abend) laufen ohne manuelles Zutun.
- Info-Displays zeigen pro Display konfigurierbare Grafana-Boards, eigene Display-Seiten und standortbezogene News.
- Alle Displays werden an einer Stelle gewartet; neue Displays/Standorte ohne Programmierung.

**Nicht-Ziele**
- Kein Ein-/Ausschalten der Displays durch das System (erfolgt hardwareseitig bzw. über den Player).
- Keine Videotelefonie in Stufe 1; Anruf über 3CX ist Stufe 2.
- Keine Anbindung an ERP/TradeControl.
- Keine Inhalte aus bestehenden Intranet-Beiträgen (News werden neu und eigenständig angelegt).

**Anti-Ziele** (wäre ein Fehlschlag, auch wenn es technisch funktioniert)
- Der Gruß ist umständlich zu pflegen und wird deshalb nicht genutzt.
- Ein Display bleibt bei Netzwerk- oder Serverausfall schwarz bzw. zeigt eine Fehlermeldung.
- Das Empfangsdisplay bleibt nach einer Bedienung auf einer Unterseite oder in Englisch stehen.
- Logik verteilt sich auf mehrere Systeme (Player-Zeitpläne + WordPress), sodass niemand mehr nachvollziehen kann, warum gerade etwas angezeigt wird.

## 3. Rahmenbedingungen

| Thema | Gegebenheit |
|---|---|
| Server | Intranet-WordPress auf eigenem Proxmox (Debian), Root-Zugriff vorhanden |
| Protokoll | Intranet läuft **nur über http**; Permalinks „Einfach“ (nicht änderbar) → REST nur über `?rest_route=` |
| Netz | Displays im internen Netz, erreichen das Intranet; Inhalte sind nur intern erreichbar |
| Öffentliche Seiten | Seiten, die per QR-Code am Handy geöffnet werden, liegen auf der öffentlichen Website holz-marberger.at |
| Player | Android-App **Slide Show** (slideshow.digital) zeigt pro Display genau **eine** URL. Keine Zeitpläne im Player für Inhalte. |
| Empfangsdisplay | DKST-LED43: 1080 × 1920 Hochformat, Android 13, Infrarot-Touch, LAN/WLAN, 2 Lautsprecher, keine Kamera |
| Info-Displays | Android-TV-Boxen mit Slide Show, ohne Touch (bisherige Grafana-Anzeigen) |
| Grafana | Lokal, ohne Login; `allow_embedding = true` wird gesetzt |
| Abschaltung | Hardwareseitig/Player („Screen power“ in Slide Show wird separat getestet) – nicht Teil des Systems |

## 4. Lösungsansatz

### 4.1 Geprüfte Varianten

| Variante | Beschreibung | Bewertung |
|---|---|---|
| A – Einzelseite | HTML/JS-Block + PHP-REST-Snippet + JSON-Datei (wie Auftragsanzeige) | schnell, aber Zeitregeln, Medien, mehrere Displays und Rollen in JSON werden unwartbar |
| **B – WordPress-Plugin** ✔ | Eigene Inhaltstypen, Player-Seite ohne Theme, REST-Endpunkt pro Display | modular, Mediathek/Rollen/Editor inklusive, für beliebig viele Displays |
| C – Mix Slide Show + Daten | Zeitpläne im Player, Inhalte aus WP/n8n | Logik in zwei Systemen → verworfen (Anti-Ziel) |

**Entscheidung:** Variante B.

### 4.2 Systemüberblick

```
┌────────────── Intranet-WordPress ──────────────┐
│ Plugin „Marberger Signage“                      │
│  • Inhaltstypen (Standort, Display, Gruß, …)    │
│  • Backend-Menü „Displays“                      │
│  • Mobile Pflegemaske (Gruß / Hinweise / News)  │
│  • REST: Zustand pro Display                    │
│  • Player-Seite ohne Theme                      │
│  • Wetter-Cache (Open-Meteo, serverseitig)      │
└──────────────┬─────────────────────────────────┘
               │ http, Polling alle 30–60 s
   ┌───────────┴───────────┐
   │ Slide Show (Android)  │  zeigt 1 URL je Display:
   │ Empfang / Info        │  …/?mm_signage=<kennung>
   └───────────────────────┘
```

- **Player-Seite:** Das Plugin liefert unter `?mm_signage=<display-kennung>` eine schlanke Vollbildseite **ohne Theme** aus (eigenes HTML/CSS/JS, keine Theme-Skripte).
- **Zustand:** Die Player-Seite fragt alle 30–60 s (pro Display einstellbar) den REST-Endpunkt ab und erhält, was gerade anzuzeigen ist. Die Auswertung der Zeitregeln passiert **serverseitig** (eine Wahrheit).
- **Ausfallsicherheit:** Ist der Server nicht erreichbar, bleibt der letzte Stand sichtbar; ein dezenter Hinweis (z. B. kleiner Punkt) zeigt den Offline-Zustand nur dem Kundigen. Zusätzlich lädt die Seite einmal pro Nacht vollständig neu.
- **Medien** (Logos, Bilder, Videos) kommen aus der WP-Mediathek. Schriften werden **lokal** im Plugin mitgeliefert (kein Google-CDN).

## 5. Begriffe

| Begriff | Bedeutung |
|---|---|
| **Standort** | Ötztal Bahnhof, Silz, Eugendorf, Traunstein – mit Logo, Wetter, Öffnungszeiten, Feiertagen |
| **Display-Kategorie** | Bündel von Standardeinstellungen für gleichartige Displays (z. B. „Empfang“, „Info intern“), inkl. Typ und Standard-Abfolge |
| **Display** | Ein physischer Bildschirm mit eindeutiger Kennung, Kategorie, Standort, Format; kann einzelne Kategorie-Werte überschreiben |
| **Empfangsdisplay** | Touch-Display für Besucher (Gruß, Wegweiser, Hinweise, Geschlossen-Seite) |
| **Info-Display** | Display ohne Touch für interne Information (Abfolge, News-Laufband) |
| **Gruß** | Zeitlich begrenzte Begrüßung mit Gast-Logo |
| **Hinweis** | Zeitgesteuerter Balken über dem Inhalt (z. B. „Eingang rechts über die Haupteinfahrt“) |
| **Vollbild-Seite** | Zeitgesteuerte Seite, die den ganzen Bildschirm übernimmt (z. B. Team-Info 06:55–07:10) |
| **Display-Seite** | Eigene Inhaltsseite im WP-Editor, auswählbar in der Abfolge eines Info-Displays |
| **Abfolge** | Liste der Inhalte eines Info-Displays mit Dauer und optionalem Zeitfenster |
| **News** | Globale Meldung mit Gültigkeit und Standortauswahl, nur auf Info-Displays |
| **Schließzeit** | Längere Schließung (z. B. Betriebsurlaub über Weihnachten) für einen oder mehrere Standorte |
| **Pause / Feierabend / geschlossene Tage** | Automatisch aus den Öffnungszeiten abgeleitete Arten von „geschlossen“ (Abschnitt 10a) |

## 6. Rangfolge der Anzeige

Pro Display wird zu jedem Zeitpunkt genau ein Hauptzustand ermittelt; Balken (Hinweise und automatische Hinweise) liegen zusätzlich darüber.

| Rang | Zustand | Empfangsdisplay | Info-Display |
|---|---|---|---|
| 1 | **Vollbild-Seite** aktiv | ✔ (optional mit Besucher-Knopf) | ✔ (ohne Knopf) |
| 2 | **Gruß** aktiv – auch außerhalb der Öffnungszeiten | ✔ (Wegweiser bleibt darunter bedienbar) | – |
| 3 | **Schließzeit** (z. B. Betriebsurlaub) | gemäß Kategorie (Standard: Vollbild „Betriebsurlaub“) | gemäß Kategorie (Standard: nichts, Abfolge läuft) |
| 4 | **Feierabend / geschlossene Tage** | gemäß Kategorie (Standard: Vollbild „Geschlossen“) | gemäß Kategorie (Standard: nichts) |
| 5 | **Standard** | Startseite Wegweiser | Abfolge |
| + | **Balken** (alle gültigen, untereinander) | Hinweise, Pause, Vorwarnung, Vorankündigung – nicht auf Vollbild-Seiten (Rang 1, 3, 4) | Hinweise über dem Abfolge-Bereich – nicht auf Vollbild-Seiten; Vorankündigung im Laufband |

- Laufen zwei Vollbild-Seiten gleichzeitig, gewinnt die mit höherer Priorität, bei Gleichstand die zuletzt geänderte. Gleiches gilt für zwei Grüße.
- Wiederkehrende Vollbild-Seiten haben die Einstellung „nur an Öffnungstagen“ (Standard: ein), damit z. B. die Team-Info nicht in den Betriebsferien erscheint.
- Beispiel: Um 06:55 läuft die Team-Info, obwohl laut Öffnungszeiten noch „geschlossen“ ist. Ein Gruß für einen Abendtermin um 19:00 ist sichtbar, obwohl Feierabend ist.
- In der Pause bleibt der Wegweiser bedienbar; nur ein Balken weist auf die Pause hin (Standard).
- **Besucher-Knopf** auf einer Vollbild-Seite zeigt immer den Zustand der **nächsten Rangstufe** (z. B. um 06:55 die Geschlossen-Seite „ab 07:00 Uhr für Sie da“ mit Knopf „Wegweiser öffnen“).
- Ein Gruß außerhalb der Öffnungszeiten zeigt darunter den normalen Wegweiser (bewusst so belassen).
- **Höchstens 3 Balken** gleichzeitig, Reihenfolge: (1) Pause/Vorwarnung, (2) eigene Hinweise nach Priorität, (3) Vorankündigungen. Was keinen Platz hat, entfällt.

## 7. Empfangsdisplay

### 7.1 Aufbau

```
Hochformat 1080×1920                    Querformat 1920×1080
┌─────────────────────────┐            ┌──────────────────────────────────────┐
│ [Logo]        ☀ 4°C 16:45│  ~25 %     │[Logo] Willkommen bei   ☀ 4°C  16:45 │
│ Willkommen bei          │            │       Holz Marberger                 │
│ Holz Marberger          │            │       Montag, 16. November 2026      │
│ Montag, 16. November    │            ├──────────────────────────────────────┤
├─────────────────────────┤            │ [Hinweis-Balken, falls aktiv]        │
│ [Hinweis-Balken]        │            │ Was brauchen Sie?                    │
│ Was brauchen Sie?       │  ~75 %     │ ┌──────┐┌──────┐┌──────┐┌──────┐     │
│ ┌────────┐┌────────┐    │            │ │Kachel││Kachel││Kachel││Kachel│     │
│ │ Kachel ││ Kachel │    │            │ └──────┘└──────┘└──────┘└──────┘     │
│ └────────┘└────────┘    │            │ [Deutsch][English]                   │
│ ┌────────┐┌────────┐    │            └──────────────────────────────────────┘
│ │ Kachel ││ Kachel │    │
│ └────────┘└────────┘    │
│ [Deutsch][English]      │
└─────────────────────────┘
```

- **Kopfbereich (oberes Viertel):** Logo des Standorts, „Willkommen bei“ (Zeile 1) / „Holz Marberger“ (Zeile 2), Tagesdatum, Wettersymbol mit Temperatur, Uhrzeit. EN: „Welcome to“ / „Holz Marberger“.
- **Wegweiser (untere drei Viertel):** Überschrift „Was brauchen Sie?“ / „How can we help you?“, Kacheln, Sprachwahl DE/EN.

### 7.2 Gruß aktiv

- Der Kopfbereich bleibt unverändert.
- Der Gruß nimmt etwa **die Hälfte des unteren Bereichs** ein: grüne Karte mit Gast-Logo (weißes Feld), Begrüßungszeile, Name/Gruppe, Zusatzzeile; EN-Texte, sofern gepflegt.
- Darunter: „Was brauchen Sie?“ + Sprachwahl in einer Zeile, darunter die **Kacheln klein nebeneinander** (Symbol + Titel; im Querformat Symbol und Titel nebeneinander).
- Gilt auch, wenn zusätzlich Hinweise aktiv sind (Gruß wird dann entsprechend kleiner).

### 7.3 Wegweiser

- Die Kacheln sind **frei pflegbar** (Wegweiser-Knoten, 9.3) durch Administratoren und Display-Redaktion; Stufe 1 startet mit vier Kacheln: Verkauf konstruktiv, Verkauf dekorativ, Verwaltung, Paketablieferung (mit Unterschrift).
- **Knoten-Baum:** Ein Knoten hat **entweder** Unterknoten (→ Unterseite mit Kacheln und „Zurück“) **oder** Detailinhalt (→ Detailseite), nie beides.
- **Layout-Regeln nach Anzahl der Kacheln je Ebene** (max. 8, mehr wird im Backend gesperrt → Untermenü verwenden):

| Anzahl | Hochformat | Querformat | Kompakt (Gruß aktiv) |
|---|---|---|---|
| 1–2 | untereinander, groß | nebeneinander | eine Reihe |
| 3–4 | 2 × 2 (bei 3: letzte Kachel breit) | eine Reihe | eine Reihe |
| 5–6 | 2 × 3 | zwei Reihen | zwei Reihen |
| 7–8 | 2 × 4, nur Symbol + Titel | zwei Reihen | zwei Reihen |

- **Detailseite** je Ziel:
  - **Weg-Bewegtbild (optional):** startet sofort, stumm, Endlosschleife. Erlaubte Formate: animiertes **WebP** (empfohlen), animiertes **GIF** oder **MP4**. Schemenhafte Darstellung genügt (z. B. Plan mit wanderndem Punkt, 5–8 Bilder/s, max. 1080 px breit, max. 10 MB). Ohne Bewegtbild wird die Detailseite als normale Seite mit größeren Wegschritten dargestellt.
  - Wegschritte als nummerierte Liste (DE/EN).
  - Ansprechpartner mit Durchwahl (Foto optional – nur mit Einwilligung der Person).
  - Knopf „Ansprechpartner anrufen“ (Stufe 2, siehe 7.6) – in Stufe 1 ausblendbar.
  - QR-Code „Weg aufs Handy“ → Seite auf holz-marberger.at (optional je Ziel).
  - „← Zurück“.
- Kacheln und Detailseiten tragen Hinweis-Balken in kompakter Form.

### 7.4 Rücksprung und Sprache

- Zeit bis zum Rücksprung in der **Display-Kategorie** einstellbar (Vorschlag 45 s), pro Display überschreibbar.
- In den letzten 10 s erscheint ein Countdown-Ring „Zurück zur Startseite in X s“.
- Rücksprung setzt Startseite **und** Deutsch zurück; ein aufgerufener Besucher-Modus während einer Vollbild-Seite endet, die Vollbild-Seite erscheint wieder.
- Jede Berührung setzt den Zähler zurück.

### 7.5 Geschlossen-Seite, Pause, Vorwarnung

- **Geschlossen-Seite** (Feierabend, geschlossene Tage): „Wir haben geschlossen.“ / „We are closed.“, nächste Öffnung nach Abschnitt 10a, Tabelle der Öffnungszeiten DE/EN, Hinweis auf abweichende Zeiten. Knopf „Wegweiser öffnen · Open directory“ (Einstellung der Kategorie).
- **Pause** (Lücke zwischen zwei Öffnungsblöcken): Balken „Mittagspause – heute ab 13:00 Uhr sind wir wieder für Sie da.“; Wegweiser bleibt bedienbar.
- **Vorwarnung vor Schluss:** X Minuten (Standard 15) vor Ende eines Öffnungsblocks. Vor einer Pause: „Mittagspause von 12:00 bis 13:00 Uhr.“ Nur vor dem letzten Block des Tages: „Wir schließen heute um 17:00 Uhr.“

### 7.5a Schließzeit (Betriebsurlaub)

- **Vorankündigung** ab X Tagen vorher (Standard 14, je Schließzeit änderbar): Balken „Betriebsurlaub 24.12.–6.1. · ab Donnerstag, 7. Jänner, wieder für Sie da.“
- **Während der Schließzeit:** Vollbild-Seite mit Bezeichnung DE/EN (z. B. „Betriebsurlaub / Company holidays“), optionalem Text (z. B. Weihnachtswünsche) und optionalem Bild, nächster Öffnung und Wegweiser-Knopf (Einstellung der Kategorie).

### 7.6 Anruf über 3CX (Stufe 2)

- WebRTC-Anruf direkt aus der Detailseite an die Durchwahl des Ziels; Freisprechen über Display-Lautsprecher + **USB-Mikrofon** (nachzurüsten).
- Slide Show: Einstellung „Grant web pages permissions“ aktivieren (Mikrofonfreigabe für Webseiten).
- **Voraussetzung https:** Android gibt das Mikrofon nur auf https-Seiten frei. Für Stufe 2 wird die Player-Seite zusätzlich über einen eigenen Hostnamen mit internem Zertifikat (https) ausgeliefert; Stufe 1 läuft über http (Entscheidung 16.11.2026).
- Overlay „Verbinde …“ mit „Auflegen“; Rücksprung-Zähler pausiert während des Gesprächs.

### 7.7 Bedienung und Gestaltung

- Infrarot-Touch löst schon kurz vor dem Glas aus → große Flächen, viel Abstand, **keine Wisch-Gesten**, Auslösen nur beim Antippen.
- Kacheln mindestens ca. 4 × 4 cm Trefffläche, Knöpfe mindestens 100 px hoch (bei 1080 px Breite).
- Farben/Schriften gemäß Styleguide „Lieferbeleg digital“: Grün #006729 (Hellgrün #e3f0e7), Rot #922224, Text #1f2421, gedämpft #5d6660, Linien #d6d9d7, Hinweis/Warnung #b86e00 auf #fff3df; Überschriften Poppins, Fließtext TeX Gyre Heros (Fallback Arial).
- Optik verbindlich: so nah wie möglich am Klickdummy.

## 8. Info-Display

### 8.1 Aufbau

```
┌──────────────────────────────────────────────────────────┐
│ [Logo]  Zuschnitt – Auftragsreihenfolge  ●○○○   ☀ 4°C 17:09│  Kopfzeile
├──────────────────────────────────────────────────────────┤  Fortschrittsbalken
│ [Hinweis-Balken, falls aktiv]                              │
│                                                            │
│           Wechselnder Inhalt der Abfolge                   │
│   (Grafana-Board / News-Folie / Display-Seite)             │
│                                                            │
├──────┬───────────────────────────────────────────────────┤
│ NEWS │ Neue Hobelmaschine ab Dezember ◆ Weihnachtsfeier … │  Laufband
└──────┴───────────────────────────────────────────────────┘
```

- Fester Rahmen: Kopfzeile (Logo des Standorts, Titel des aktuellen Inhalts, Positionspunkte, Wetter, Uhr), Fortschrittsbalken bis zum nächsten Wechsel, Laufband unten.
- Nur der mittlere Bereich wechselt. Keine Bedienung.
- Hoch- und Querformat werden unterstützt.

### 8.2 Abfolge

Pro Display-Kategorie eine Standard-Abfolge; ein Info-Display übernimmt sie oder hat eine eigene. Eine Abfolge ist eine geordnete Liste von Einträgen:

| Feld | Inhalt |
|---|---|
| Typ | Web-Seite (URL – Grafana-Board oder andere Intranet-Seite ohne Login) · News-Folie · Display-Seite (Auswahl) · *(Stufe 3: Bild, Video)* |
| Titel | erscheint in der Kopfzeile |
| Dauer | Standard **30 s** (Einstellung der Kategorie), pro Eintrag überschreibbar |
| Zeitfenster | optional, gleiche Zeitregel wie überall (Abschnitt 10); außerhalb fällt der Eintrag aus der Abfolge |
| Skalierung | Design-Auflösung des Inhalts **pro Eintrag** (Standard 1920 × 1080), wird auf den verfügbaren Bereich eingepasst |

- Rotation und Zeitfenster sind kombinierbar („beides muss möglich sein“).
- Ist kein Eintrag gültig, erscheint eine neutrale Standardseite (Logo, Datum, Uhr).
- Eine News-Folie ohne gültige News wird **übersprungen**.
- Die Abfolge wird **nur von Administratoren** im Backend eingestellt.

### 8.3 Grafana-Einbettung

- Boards werden per iframe in ihrer Design-Auflösung geladen und per CSS auf den Mittelbereich **verkleinert**, sodass sie vollständig auf einen Bildschirm passen (kein Scrollen).
- Damit entfällt das bisherige Problem der begrenzten Rasterzeilen auf Android-TV (DevicePixelRatio).
- Voraussetzung: `allow_embedding = true` in der Grafana-Konfiguration; Kiosk-Parameter in der URL.
- Damit beim Wechsel kein leerer Rahmen erscheint, wird **nur das nächste** Board unsichtbar vorgeladen (nicht alle – schwache TV-Boxen).
- Lesbarkeit: Verkleinerte Boards sind aus Distanz schwer lesbar → für Displays eigene Boards mit größerer Schrift anlegen bzw. die Design-Auflösung je Eintrag anpassen.

### 8.4 News

- **Vorankündigungen von Schließzeiten** erscheinen auf Info-Displays automatisch im Laufband und auf der News-Folie (ohne eigene News-Anlage).
- Als **Folie** (Typ „News-Folie“ in der Abfolge: zeigt die gültigen News des Standorts nacheinander, Bild + Titel + Text + „gültig bis“) und/oder im **Laufband** – pro News wählbar.
- Nur auf Info-Displays; Empfangsdisplays zeigen keine News.

## 9. Datenmodell (Inhaltstypen und Felder)

Alle Inhalte sind eigene WordPress-Inhaltstypen bzw. Einstellungen des Plugins. Pflichtfelder mit *.

### 9.1 Standort
| Feld | Typ |
|---|---|
| Name* | Text (Ötztal Bahnhof, Silz, Eugendorf, Traunstein) |
| Logo* | Bild (nur für die Displays; Holzzentrum Traunstein eigenes Logo) |
| Koordinaten* | Breite/Länge (Wetter) |
| Feiertagskalender* | Österreich / Bayern; jeder Feiertag einzeln auf „trotzdem geöffnet“ stellbar (z. B. 8.12.) |
| Öffnungsblöcke | je Wochentag beliebig viele Blöcke (z. B. 07:00–12:00, 13:00–17:00) oder „geschlossen“ |
| Ausnahmen | einzelner Tag + „geschlossen“ oder abweichende Zeiten + Bezeichnung (z. B. 23.12. nur bis 12:00, Fenstertag); optional „ankündigen ab X Tagen“. Mehrtägige Schließungen → Schließzeit (9.9) |

Pflege: nur Administratoren im WP-Backend (am Standort).

### 9.2 Display-Kategorie

Bündelt die Standardwerte; jedes Display übernimmt sie und kann einzelne Werte überschreiben.

| Feld | Beispiel „Empfang“ | Beispiel „Info intern“ |
|---|---|---|
| Bezeichnung* | Empfang | Info intern |
| Typ* | Empfang (Touch) | Info (ohne Touch) |
| Abfrageintervall | 60 s | 60 s |
| Rücksprung zur Startseite | 45 s | – |
| Anzeige in der Pause | Balken | nichts |
| Anzeige bei Feierabend / geschlossenen Tagen | Vollbild „Geschlossen“ | nichts (Abfolge läuft) |
| Anzeige während Schließzeit | Vollbild „Schließzeit“ | nichts (Abfolge läuft) |
| Vorwarnung vor Schluss | ein, 15 min | aus |
| Vorankündigung Schließzeit | Balken | Laufband + News-Folie |
| Wegweiser-Knopf auf Geschlossen-/Schließzeit-Seite | ein | – |
| News / Laufband | aus | ein |
| Standard-Dauer pro Abfolge-Eintrag | – | 30 s |
| Standard-Abfolge | – | Liste (siehe 8.2) |

Mögliche Werte für die Anzeige-Felder: **Vollbild-Seite**, **Balken**, **nichts**.

### 9.2a Display
| Feld | Typ |
|---|---|
| Kennung* | Slug, Teil der URL (z. B. `empfang-oetztal`, `info-oetztal`) |
| Bezeichnung* | Text |
| Kategorie* | Verweis |
| Standort* | Verweis |
| Format* | Hoch / Quer |
| Wegweiser-Startknoten | Verweis (nur Typ Empfang) |
| Abfolge | „von Kategorie übernehmen“ oder eigene Liste (nur Typ Info) |
| Abweichungen | jeder Wert der Kategorie einzeln überschreibbar |

### 9.3 Wegweiser-Knoten
| Feld | Typ |
|---|---|
| Titel DE*, Titel EN | Text |
| Untertitel DE/EN | Text |
| Symbol | Auswahl aus Symbol-Set |
| Eltern-Knoten, Reihenfolge | Verweis, Zahl |
| Weg-Bewegtbild | Medium, optional (WebP/GIF animiert oder MP4) |
| Wegschritte DE/EN | Liste |
| Ansprechpartner, Durchwahl | Text |
| Foto Ansprechpartner | Bild, optional (Hinweis: nur mit Einwilligung) |
| Unterknoten **oder** Detailinhalt | Ein Knoten mit Unterknoten hat keinen Detailinhalt |
| QR-Ziel | URL (öffentliche Website) |
| Anruf-Ziel | Durchwahl (Stufe 2) |

Pflege: Administratoren und Display-Redaktion.

### 9.4 Gruß
| Feld | Typ |
|---|---|
| Begrüßungszeile DE (Standard „Herzlich willkommen“), EN | Text |
| Name / Gruppe* | Text |
| Zusatzzeile DE/EN | Text |
| Gast-Logo | Bild (Upload vom Handy) |
| Von (Tag, Startzeit)*, Bis (Tag, Endzeit)* | Datum/Zeit |
| Nur täglich im Zeitfenster | ja/nein (bei mehrtägigen Grüßen) |
| Displays* | Mehrfachauswahl (nur Empfangsdisplays) |

**Schnellvorlagen** (vorbefüllte Texte, im Backend pflegbar): Schulklasse, Kundenbesuch, Delegation/Gruppe, Schnuppertag/Lehre.

**Regeln**
- Speichern ist gesperrt, solange Platzhalter in eckigen Klammern (z. B. „[Schule]“) im Text stehen.
- Name/Gruppe max. 2 Zeilen; die Schrift verkleinert sich automatisch, Zeichengrenze mit Live-Vorschau im Formular.
- Gast-Logo: Beim Hochladen wird verkleinert und in JPG/PNG umgewandelt (auch iPhone-HEIC); ist das nicht möglich, erscheint eine klare Fehlermeldung.
- Grüße samt Logo werden **30 Tage nach Ablauf automatisch gelöscht**.

### 9.5 Hinweis
| Feld | Typ |
|---|---|
| Text DE*, Text EN | Text |
| Symbol | Pfeil rechts / links / geradeaus / Info / Warnung |
| Wochentage*, Von* | Mehrfachauswahl, Uhrzeit |
| Bis | Uhrzeit **oder** „bis Geschäftsschluss“ = Ende des **letzten** Öffnungsblocks des Tages; an Tagen ohne Öffnung erscheint der Hinweis nicht |
| Gültig von/bis | optional (z. B. Baustelle) |
| Displays* | Mehrfachauswahl (Empfang und Info) |
| Aktiv | ja/nein |

Mehrere aktive Hinweise werden **untereinander** angezeigt (Reihenfolge nach Priorität, dann Änderungsdatum).

### 9.6 News
| Feld | Typ |
|---|---|
| Titel* | Text |
| Text | Text |
| Bild | Bild |
| Gültig von* / bis* | Datum |
| Standorte* | Mehrfachauswahl (alle / einzelne) |
| Als Folie / im Laufband | je ja/nein |

### 9.7 Vollbild-Seite
| Feld | Typ |
|---|---|
| Titel | Text |
| Inhalt | WP-Editor, eingeschränkt auf Überschrift, Absatz, Liste, Bild, Spalten |
| Zeitregel* | siehe Abschnitt 10 (z. B. Mo–Fr 06:55–07:10) |
| Displays* | Mehrfachauswahl |
| Besucher-Knopf („Besucher? Hier tippen“) | ja/nein (nur Empfang wirksam) |
| Nur an Öffnungstagen | ja/nein (Standard ja; bei wiederkehrenden Zeitregeln) |
| Priorität | Zahl |

### 9.8 Display-Seite
| Feld | Typ |
|---|---|
| Titel | Text |
| Inhalt | WP-Editor, eingeschränkt auf Überschrift, Absatz, Liste, Bild, Spalten (deren Styles werden auf der Player-Seite mitgeladen) |
| Format | Quer / Hoch (für Vorschau und Layout) |

Eigener Inhaltstyp statt normaler WP-Seiten: nicht in Intranet-Navigation/Suche, eigene Rechte, feste Bildschirmvorlage mit Vorschau.

### 9.9 Schließzeit
| Feld | Typ |
|---|---|
| Bezeichnung DE*, EN | Text (z. B. Betriebsurlaub / Company holidays) |
| Von* / bis* | Datum |
| Standorte* | Mehrfachauswahl (abweichende Zeiten je Standort = eigene Schließzeit) |
| Text DE/EN | Text (optional, z. B. Weihnachtswünsche) |
| Bild | Bild (optional) |
| Vorankündigung ab | Tage vorher (Standard 14) |

Pflege: Administratoren im Backend.

## 10. Zeitregeln (einheitlich)

Alle zeitgesteuerten Inhalte verwenden dieselbe Logik:
- **Einmalig:** von (Datum + Uhrzeit) bis (Datum + Uhrzeit).
- **Wiederkehrend:** Wochentage + Zeitfenster, optional begrenzt durch „gültig von/bis“.
- **Mehrtägig mit Tagesfenster:** z. B. 20.–21.11., jeweils 09:00–16:00.
- **Bezug auf Öffnungszeiten:** „bis Geschäftsschluss“ (Hinweise).
- Zeitfenster über Mitternacht sind erlaubt (z. B. 22:00–02:00); widersprüchliche Eingaben (Ende vor Beginn bei Datumsbereichen) verhindert das Formular.
- Zeitzone Europe/Vienna für alle Standorte (Traunstein identisch).
- Feiertage je Standort automatisch berechnet (AT bzw. BY), einzeln auf „trotzdem geöffnet“ stellbar; Ausnahmen manuell.

## 10a. Regelwerk Öffnungszeiten

**Grundregel:** Jede Zeit außerhalb eines Öffnungsblocks gilt als geschlossen. Ausnahmen ersetzen den ganzen Tag; Schließzeiten überlagern die Öffnungszeiten.

**Arten von „geschlossen“**
| Art | Erkennung | Standard-Darstellung Empfang |
|---|---|---|
| Pause | Lücke zwischen zwei Blöcken am selben Tag | Balken |
| Feierabend | nach dem letzten Block bis zum ersten Block des Folgetags | Vollbild „Geschlossen“ |
| Geschlossene Tage | ein oder mehrere ganze Tage ohne Block (Wochenende, Feiertag) | Vollbild „Geschlossen“ |
| Schließzeit | Zeitraum einer Schließzeit (9.9) | Vollbild „Schließzeit“ |

**Weitere Regeln**
- **Kurze Lücken ignorieren:** Lücken unter einer einstellbaren Dauer (Standard 15 min) gelten nicht als Pause.
- **Zusammenhängende Zeiträume verbinden:** Feiertage, Wochenende und Schließzeit, die direkt aneinander anschließen, gelten als ein Zeitraum (am 24.12. steht bereits „ab 7. Jänner“).
- **Nächste Öffnung** wird bis 60 Tage voraus gesucht; findet sich keine, steht „Unsere Öffnungszeiten finden Sie auf holz-marberger.at“.

**Formulierung „wieder für Sie da“** (DE/EN)
- selber Tag: „Heute ab 13:00 Uhr …“
- Folgetag: „Morgen ab 07:00 Uhr …“
- innerhalb von 6 Tagen: „Am Montag ab 07:00 Uhr …“
- später: „Am Montag, 7. Jänner, ab 07:00 Uhr …“
- Grund nur, wenn bekannt: „Mittagspause“, Bezeichnung des Feiertags bzw. der Ausnahme/Schließzeit.

## 11. Rollen und Pflege

| Wer | Wo | Darf |
|---|---|---|
| **Administrator** | WP-Backend | alles: Standorte, Öffnungszeiten, Schließzeiten, Display-Kategorien, Displays, Abfolgen, Wegweiser, Vorlagen, Grafana-URLs |
| **Display-Redaktion** (Häkchen „Display-Redaktion“ im WP-Benutzerprofil; vergibt die Rechte **zusätzlich** zur bestehenden Rolle) | WP-Backend (Menü „Displays“ mit Grüße, Hinweise, News als eigener Punkt, Vollbild-Seiten, Display-Seiten, Wegweiser) **und** mobile Pflegemaske | Grüße, Hinweise, News, Vollbild-Seiten, Display-Seiten, **Wegweiser-Kacheln** anlegen/ändern/löschen; **Gruß-Passwort einsehen** |
| **Empfang (gemeinsames Passwort)** | mobile Pflegemaske | **nur Grüße** anlegen/ändern/löschen |

### 11.1 Mobile Pflegemaske
- Eigene, handytaugliche Seite im Intranet (wie im Klickdummy). Erreichbar **nur im internen Netz** (Firmen-WLAN); Pflege von außen ist nicht vorgesehen.
- Anmeldung entweder mit **gemeinsamem Gruß-Passwort** (→ nur Reiter „Begrüßungen“) oder mit **persönlichem WP-Login** mit Berechtigung Display-Redaktion (→ Reiter „Begrüßungen“, „Hinweise“, „News“).
- Gruß-Passwort: verschlüsselt gespeichert und im Backend unter „Displays → Einstellungen“ **abrufbar** (Administratoren und Display-Redaktion), änderbar durch Administratoren. Sitzung bleibt auf dem Handy 30 Tage angemeldet; eine Passwortänderung meldet alle Handys ab.
- Risiko eines gemeinsamen Passworts im Firmen-WLAN (jeder mit Passwort kann einen Gruß setzen) ist bewusst akzeptiert; kein zusätzliches Protokoll.
- Nach dem Speichern: Hinweis „erscheint am Display binnen 60 s“.

## 12. Technische Umsetzung

### 12.1 Plugin-Bestandteile
- Inhaltstypen und Felder (Abschnitt 9), Backend-Menü „Displays“, Berechtigung „Display-Redaktion“ (Häkchen im Benutzerprofil, eigene Capabilities).
- Player-Seite `?mm_signage=<kennung>` ohne Theme; eine gemeinsame JS-Anwendung für beide Display-Typen und Formate.
- REST-Endpunkte (über `?rest_route=`):
  - `GET /mms/v1/state/<kennung>` – öffentlich im Intranet, liefert den aufgelösten Zustand (Rang, Inhalte, Hinweise, Abfolge, Wetter, Öffnungszeiten, nächste Öffnung, Server-Uhrzeit).
  - Schreibende Endpunkte der Pflegemaske mit Authentifizierung (WP-Cookie + Nonce bzw. Gruß-Passwort-Token).
- Wetter: serverseitiger Abruf bei Open-Meteo je Standort, Zwischenspeicher 30 min; Displays brauchen keinen Internetzugang.
- Der Zustands-Endpunkt wird ausdrücklich von Seiten-/Server-Caches ausgenommen (Prüfpunkt bei Inbetriebnahme).
- Bild-Uploads (Gast-Logo, News, Display-Seiten) werden serverseitig verkleinert und bei Bedarf umgewandelt (HEIC → JPG).

### 12.2 Player
- Uhr läuft lokal, wird mit der Server-Uhrzeit aus dem Zustand abgeglichen.
- Zustandswechsel ohne Flackern (neuen Inhalt vorladen, dann umschalten).
- Letzter Zustand bleibt im Speicher; kein Service Worker (http).
- Nächtlicher Vollneuladen (z. B. 03:00) – nur nach erfolgreicher Erreichbarkeitsprüfung des Servers.
- Neustart bei Serverausfall: Slide Show zeigt laut Dokumentation nur erfolgreich geladene Webseiten an. Zusätzlich wird in Slide Show ein lokales Ersatzbild (Logo) hinterlegt, das erscheint, solange die Seite nicht lädt.
- Bewegtbild: WebP/GIF laufen als Bild ohne Autoplay-Beschränkung; MP4 vor Einsatz am Gerät testen (Autoplay-Freigabe der WebView in Slide Show).
- Zielplattform: Android System WebView der eingesetzten Geräte; vor Umsetzungsbeginn auf den TV-Boxen und dem DKST-LED43 die WebView-Version prüfen und danach die JS/CSS-Basis festlegen (Entwickler).

### 12.3 Betrieb
- Slide Show je Display: eine Web-URL, „Keep screen on“, Autostart; für Stufe 2 „Grant web pages permissions“.
- Grafana: `allow_embedding = true`.
- **Lebenszeichen:** Jedes Display meldet bei jeder Abfrage „zuletzt gesehen“; das Backend zeigt pro Display Status (online/offline seit …).

## 13. Ausbaustufen

| Stufe | Umfang |
|---|---|
| **1 (Start)** | Standorte inkl. Öffnungszeiten/Feiertage/Schließzeiten und Regelwerk (10a), Display-Kategorien, Displays, Empfangsdisplay komplett (Kopf, Wegweiser mit 4 Zielen und optionalem Weg-Bewegtbild, Gruß inkl. Vorlagen, Hinweise, Geschlossen-Seite, Vollbild-Seiten, Rücksprung, DE/EN), Info-Display komplett (Abfolge mit Grafana, News-Folie, Display-Seiten, Laufband), Rollen, mobile Pflegemaske, Lebenszeichen |
| **2** | Anruf über 3CX (WebRTC) inkl. USB-Mikrofon; Klick-Zählung je Kachel (Auswertung in Grafana) |
| **3** | Weitere Abfolge-Typen (Bild, Video), weitere Sprachen, weitere Displays/Standorte nach Bedarf; Ein-/Ausschalten der Displays über die Slide-Show-Schnittstelle, abgeleitet aus den Öffnungszeiten (beseitigt die doppelte Pflege von Abschaltzeiten) |

## 14. Offene Punkte und Zuarbeit

| Nr. | Punkt | Wer |
|---|---|---|
| 1 | Echte Wegbeschreibungen, Ansprechpartner und Durchwahlen der vier Ziele | Holz Marberger |
| 2 | Weg-Bewegtbilder erstellen (schemenhaft, WebP/GIF/MP4, 5–8 Bilder/s) oder bewusst ohne | Holz Marberger |
| 2a | Test am DKST-LED43: Klickdummy mit `medium=gif`, `webp`, `mp4` | Holz Marberger |
| 3 | Öffnungszeiten und Ausnahmen je Standort | Holz Marberger |
| 4 | Logos je Standort (Holzzentrum Traunstein) | Holz Marberger |
| 5 | Liste der Grafana-Boards je Info-Display inkl. URL | Holz Marberger |
| 6 | Intranet-Server darf Open-Meteo abfragen (ausgehend http/https)? | IT |
| 7 | Öffentliche QR-Zielseiten auf holz-marberger.at (je Ziel) | Holz Marberger |
| 8 | Einwilligungen für Fotos von Ansprechpartnern (falls Fotos gewünscht) | Holz Marberger |

## 15. Entscheidungsprotokoll (16.11.2026)

- Umsetzung als eigenes WordPress-Plugin im Intranet; Start mit Klickdummy.
- Empfangsdisplay: Standard-Kopf im oberen Viertel; aktiver Gruß belegt die Hälfte des unteren Bereichs, Kacheln dann klein nebeneinander.
- Wegweiser Stufe 1: Verkauf konstruktiv, Verkauf dekorativ, Verwaltung, Paketablieferung mit Unterschrift; Weg-Bewegtbild startet sofort.
- Mehrsprachig DE/EN.
- Rücksprung zur Startseite nach einstellbarer Zeit.
- Gruß mehrtägig möglich; Schnellvorlagen mit Logo; Pflege mit gemeinsamem Passwort bleibt.
- Standardkopf: „Willkommen bei“ / „Holz Marberger“ zweizeilig, Logo, Tagesdatum, Wettersymbol.
- Team-Seite als Texteditor mit Bild; Wegweiser während der Team-Seite optional erreichbar.
- Geschlossen-Seite mit Öffnungszeiten automatisch außerhalb der Öffnungszeiten.
- Hinweise pro Display, zeitgesteuert, „bis Geschäftsschluss“ möglich; mehrere untereinander; nicht auf Vollbild- und Geschlossen-Seiten.
- Öffnungszeiten pro Standort, Pflege im WP-Backend.
- Display-Abschaltung hardwareseitig, nicht Teil des Systems.
- Alle Displays (auch bisherige Grafana-Anzeigen) werden über dieses System gepflegt; Info-Displays ohne Touch.
- Grafana-Seiten ohne Login, verkleinert auf eine Bildschirmhöhe; `allow_embedding` wird gesetzt.
- Info-Display mit festem Rahmen (Kopfzeile, Laufband); Rotation und Zeitfenster kombinierbar; Standard 30 s, pro Seite festlegbar.
- News neu und eigenständig (nicht bestehende Beiträge), mit gültig von/bis, Standortauswahl; als Folie und Laufband; nur auf internen Displays.
- Rolle „Display-Redaktion“ für persönliche Benutzer; News als eigener Menüpunkt.
- Abfolge nur durch Administratoren.
- Standorte: Ötztal Bahnhof, Silz, Eugendorf, Traunstein; Logo pro Standort nur auf den Displays.
- Persönliche WP-Benutzer mit (zusätzlicher) Berechtigung „Display-Redaktion“; Gruß bleibt zusätzlich über gemeinsames Passwort pflegbar.
- Info-Displays zeigen beliebige Intranet-Seiten ohne Login; Pflegemaske nur intern erreichbar.
- Stufe 1 über http; https erst für den 3CX-Anruf in Stufe 2.
- Regelwerk Öffnungszeiten (10a): Pause als Balken, Feierabend/geschlossene Tage als Vollbild, kurze Lücken (< 15 min) ignorieren, Vorwarnung vor Schluss.
- Schließzeiten (z. B. Betriebsurlaub) als eigener Baustein für mehrere Standorte, Vorankündigung 14 Tage vorher; kein Notfallkontakt.
- Darstellung von Schließzeiten und Geschlossen-Zuständen je Display-Kategorie, pro Display überschreibbar; Info-Displays laufen in den Betriebsferien normal weiter.
- Display-Kategorien mit Standardwerten und Standard-Abfolge; Displays können übernehmen oder abweichen.
- Ein geplanter Gruß hat Vorrang vor „Geschlossen“.
- Gruß-Passwort im Backend abrufbar (Administratoren und Display-Redaktion); Risiko gemeinsames Passwort akzeptiert.
- Wegweiser-Kacheln frei pflegbar durch Administratoren und Display-Redaktion; Layout-Regeln für 1–8 Kacheln; Unterknoten **oder** Detailinhalt.
- Weg-Bewegtbild optional; schemenhafte Animation genügt (WebP/GIF/MP4); ohne Bewegtbild normale Detailseite.
- Vorwarnung unterscheidet Pause und Tagesschluss; „bis Geschäftsschluss“ = Ende des letzten Blocks.
- Besucher-Knopf zeigt den Zustand der nächsten Rangstufe; höchstens 3 Balken.
- Ausnahmen optional ankündbar; Feiertage einzeln auf „trotzdem geöffnet“ stellbar.
- Display-Redaktion als Häkchen im Benutzerprofil (zusätzlich zur bestehenden Rolle).
- Grüße werden 30 Tage nach Ablauf gelöscht; Fotos von Mitarbeitenden nur mit Einwilligung.
- Abschaltzeiten in Stufe 1 hardwareseitig (doppelte Pflege akzeptiert), Steuerung aus WordPress als Stufe 3 vorgemerkt.

## 16. Annahmen und Festlegungen durch den Konzeptersteller

Diese Punkte wurden nicht ausdrücklich besprochen und sind als Vorschlag gesetzt – bitte beim Lesen prüfen:
- Rücksprung Standard 45 s; Abfrageintervall Standard 60 s.
- Display-Redaktion darf auch Vollbild-Seiten und Display-Seiten pflegen.
- Empfehlung WebP vor GIF (bessere Qualität bei gleicher Größe); Grenzwerte 1080 px / 10 MB.
- Sitzung mit Gruß-Passwort 30 Tage auf dem Handy.
- Wetter über Open-Meteo, serverseitig zwischengespeichert.
- Lebenszeichen/Online-Status je Display im Backend.
- Gleichzeitige Grüße/Vollbild-Seiten: Priorität, dann zuletzt geändert.
- Wegweiser-Startknoten pro Display (für spätere Empfangsdisplays an anderen Standorten).
- Klick-Zählung erst in Stufe 2.
- Vorankündigung erscheint auf Info-Displays automatisch (ohne eigene News).
- Wiederkehrende Vollbild-Seiten standardmäßig „nur an Öffnungstagen“.
- Mehrere Balken im Hochformat: Kacheln zeigen dann nur Symbol und Titel.
- Abfolge-Typ allgemein „Web-Seite (URL)“ statt nur „Grafana“, damit auch andere Intranet-Seiten ohne Login möglich sind.
- Ersatzbild in Slide Show für den Fall, dass der Server beim Start nicht erreichbar ist.

## Anhang A – Klickdummy

Datei `klickdummy-empfangsdisplay.html`; am Gerät mit URL-Parametern:

| Parameter | Wirkung |
|---|---|
| `kiosk=1` | ohne Steuerleiste, Vollbild |
| `format=hoch` / `format=quer` | Format |
| `display=info` | Info-Display statt Empfang |
| `szenario=gruss` / `team` / `closed` / `pause` / `vorwarnung` / `vorank` / `urlaub` | Gruß, Team-Seite, Geschlossen-Seite, Mittagspause, Vorwarnung, Vorankündigung, Betriebsurlaub |
| `hinweis=1` | Hinweis-Balken aktiv |
| `medium=svg` / `gif` / `webp` / `mp4` / `none` | Weg-Bewegtbild auf der Detailseite (Muster nur für „Verkauf konstruktiv“) bzw. ohne |
| `ziel=konstruktiv` / `dekorativ` / `verwaltung` / `paket` | Detailseite direkt öffnen |

Alle Texte zu Wegen, Durchwahlen, Öffnungszeiten, News und Grafana-Daten im Klickdummy sind Beispielinhalte.
