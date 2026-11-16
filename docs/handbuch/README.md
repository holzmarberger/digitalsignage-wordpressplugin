# Anwenderhandbuch – Quellen und Erzeugung

Hier liegt alles, um das Anwenderhandbuch (PDF, ca. 60 Seiten) und den Aushang „Gruß vom Handy“ neu zu bauen –
bei reinen Textänderungen in einer halben Minute, bei Änderungen am Plugin mit frischen Screenshots in etwa
zehn Minuten.

```
build.py                HTML → PDF (Inhaltsverzeichnis mit Seitenzahlen, Symboltabellen, QR-Code, Aushang)
src/                    Texte als HTML, je Kapitel eine Datei; 00-head.html = Layout (CSS, Kopf-/Fußzeile)
img/                    Screenshots (komprimiert), werden von werkzeuge/ erzeugt
werkzeuge/
  erzeugen.sh           Ablaufsteuerung: umgebung · server · daten · bilder · pdf · alles
  seed.js               Beispieldaten über die echte Backend-Oberfläche anlegen
  shots.js              alle Screenshots inkl. roter Markierungen
  lib.js, pdf.js, qr.js Hilfen (Puppeteer, PDF-Druck, QR-Code mit der Bibliothek des Players)
  php/                  Hilfsskripte direkt gegen die Testinstanz (Medien, Optionen, Zusatzdaten)
  mu-plugin/            nur Testinstanz: fehlende deutsche Übersetzungen, SQLite-Hinweise ausblenden
  grafana/              Attrappe zweier Grafana-Boards für die Info-Display-Aufnahmen
  beispieldaten/        erfundene Logos und Bilder (keine echten Kunden)
build/, arbeit/         Ausgabe bzw. Arbeitsdateien (nicht im Repo)
```

## Voraussetzungen

Linux oder macOS mit `php` (8.1+, SQLite-Erweiterung), `node` (18+), Google Chrome, `python3` mit Pillow,
`pngquant`, `poppler-utils` (`pdfinfo`, `pdftotext`, `pdftoppm`), `git`. Einmalig `npm install` in `werkzeuge/`
(macht `erzeugen.sh umgebung` automatisch). Abweichender Chrome-Pfad: `CHROME=/pfad/zu/chrome`.

## Nur Texte geändert

```bash
python3 build.py --bogen        # → build/handbuch.pdf, build/aushang.pdf, Kontrollbögen build/bogen-XX.png
```

Die Kontrollbögen zeigen je sechs Seiten – damit lassen sich Lücken und Umbrüche schnell prüfen. Die
Seitenzahlen im Inhaltsverzeichnis setzt `build.py` selbst (zwei Durchläufe).

**Versionsangaben** beim Aktualisieren anpassen: Fußzeile in `src/00-head.html` (`@bottom-left`) und Tabelle
„Version / Stand“ auf dem Deckblatt in `src/01-cover-intro.html`.

## Plugin geändert – Screenshots neu

```bash
cd werkzeuge
./erzeugen.sh alles             # frische Testinstanz, Beispieldaten, alle Screenshots, PDF mit Kontrollbögen
```

oder schrittweise:

```bash
./erzeugen.sh umgebung          # WordPress + SQLite unter ~/hb-wp (WP_DIR), Plugin als Symlink auf dieses Repo
./erzeugen.sh server            # PHP-Server auf http://localhost (Port 80) und Grafana-Attrappe auf :3000
./erzeugen.sh daten             # Beispieldaten – nur einmal je Testinstanz
./erzeugen.sh bilder            # alle Screenshots nach img/
./erzeugen.sh bilder '^d-info'  # nur ausgewählte (regulärer Ausdruck auf den Bildnamen)
./erzeugen.sh pdf --bogen
```

Neustart von null: `rm -rf ~/hb-wp docs/handbuch/arbeit` und erneut `alles`.

Bildnamen in `shots.js` und `src/`: `b-` Backend · `d-` Display · `x-` Display mit Feldnummern („welcher Text wo“) ·
`s-` Größenbeispiele · `m-` Handy-Pflegemaske. Rote Ziffern markieren Felder (gleiche Nummer in Maske und
Display-Bild), rote Buchstaben automatisch erzeugte Teile.

### Gut zu wissen

- **Zeitpunkte:** Alle Display-Aufnahmen setzen den Zeitpunkt über `&at=…` (Beispieldaten liegen im
  Dezember 2026). Sie bleiben also auch später reproduzierbar.
- **Adressen:** Der Testbrowser erreicht nur `localhost`. `lib.js → fixUrls()` schreibt in den Screenshots
  sichtbare Adressen auf `intranet-marberger.oetztal.holz-marberger.at` bzw. `grafana.oetztal.holz-marberger.at:3000`
  um. Die Adresse im QR-Code des Aushangs lässt sich mit `HB_MASK_URL` ändern.
- **Sprache:** Ohne Internetzugang zu wordpress.org kommen die deutschen Sprachdateien aus dem Ubuntu-Paket
  `wordpress-l10n` (älterer Stand); `mu-plugin/hb-cosmetics.php` ergänzt die fehlenden Texte. Besser:
  `HB_LANG_DIR=/pfad/zu/wp-content/languages` (Sprachdateien aus dem Intranet) beim Aufsetzen angeben.
- **WordPress-Version:** Standard ist der neueste Stand aus dem offiziellen Git-Spiegel; mit `WP_REF=6.8.3`
  (Tag) lässt sich die Intranet-Version nachstellen.
- **Zugangsdaten der Testinstanz:** `admin` / `admin123`, Redaktion `m.muster` / `Redaktion!2026x`,
  Gruß-Passwort `Empfang2026!` – nur für die lokale Testinstanz.
- **Neue Masken oder Felder:** Markierungen in `shots.js` (`mark()` mit `row:<Feldbeschriftung>`, CSS-Selektor
  oder `text:<tag>|<Anfang>`) und die Feldtabellen im jeweiligen Kapitel nachziehen. Fehlende Markierungsziele
  meldet `shots.js` als `MARK MISSING`.
