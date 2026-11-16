<?php
namespace Marberger\Signage\WP\Admin;

use Marberger\Signage\WP\PostTypes;

/**
 * Erklärtexte direkt im Backend: ausführlich über den Listen, knapp über den Bearbeiten-Masken.
 * Die Texte entsprechen dem Anwenderhandbuch.
 */
final class Help
{
    public static function init(): void
    {
        foreach (array_keys(self::texts()) as $type) {
            add_filter('views_edit-' . $type, static function ($views) use ($type) {
                echo self::box($type, false); // phpcs:ignore -- in box() maskiert
                return $views;
            });
        }
        add_action('edit_form_after_title', [self::class, 'editBox']);
    }

    /** @return array<string, array{title:string, lead:string, points:list<string>}> */
    public static function texts(): array
    {
        return [
            PostTypes::GREETING => [
                'title' => 'Grüße – persönliche Begrüßung am Empfangsdisplay',
                'lead' => 'Ein Gruß erscheint im Zeitraum „Von – Bis“ auf den gewählten Empfangsdisplays als grüne Karte in der unteren Bildschirmhälfte. Die Wegweiser-Kacheln rücken dann klein darunter und bleiben bedienbar.',
                'points' => [
                    'Ein Gruß hat Vorrang vor der Geschlossen- und der Schließzeit-Seite – ein Gruß für einen Abendtermin ist also auch nach Geschäftsschluss sichtbar. Nur eine Vollbild-Seite verdrängt ihn.',
                    '„Nur täglich von Start- bis Endzeit“: Bei mehrtägigen Besuchen erscheint der Gruß an jedem Tag des Zeitraums nur im Zeitfenster.',
                    'Laufen zwei Grüße gleichzeitig, wird der zuletzt geänderte gezeigt.',
                    'Platzhalter in [eckigen Klammern] aus den Schnellvorlagen müssen ersetzt werden – sonst wird der Gruß nicht angezeigt.',
                    'Abgelaufene Grüße werden 30 Tage nach dem Ende samt hochgeladenem Logo automatisch gelöscht.',
                    'Schneller geht es vom Handy: Adresse der Handy-Pflegemaske unter „Displays → Übersicht“.',
                ],
            ],
            PostTypes::NOTICE => [
                'title' => 'Hinweise – Balken über dem Inhalt',
                'lead' => 'Ein Hinweis ist ein orangefarbener Balken mit Symbol, z. B. „Eingang rechts über die Haupteinfahrt“. Auf Empfangsdisplays steht er über den Kacheln, auf Info-Displays über der Abfolge.',
                'points' => [
                    'Er erscheint an den gewählten Wochentagen von „Von“ bis „Bis“. „Bis Geschäftsschluss“ bedeutet: bis zum Ende der Öffnungszeit des Standorts an diesem Tag – an geschlossenen Tagen erscheint der Hinweis dann gar nicht.',
                    'Höchstens 3 Balken gleichzeitig: zuerst die automatischen (Pause, Vorwarnung vor Schluss), dann Hinweise nach Priorität, zuletzt Vorankündigungen. Was keinen Platz hat, entfällt.',
                    'Auf Vollbild-, Geschlossen- und Schließzeit-Seiten werden keine Balken gezeigt.',
                    'Mit dem Häkchen „Aktiv“ lässt sich ein Hinweis pausieren, ohne ihn zu löschen.',
                ],
            ],
            PostTypes::NODE => [
                'title' => 'Wegweiser-Kacheln – die Schaltflächen am Empfangsdisplay',
                'lead' => 'Jede Wegweiser-Kachel ist ein eigener Eintrag („Wegweiser-Kachel anlegen“). Kacheln ohne übergeordnete Kachel bilden die Startseite „Was brauchen Sie?“. Wer eine Kachel antippt, sieht entweder eine Auswahl weiterer Kacheln oder die Wegbeschreibung.',
                'points' => [
                    'Untermenü: bei der Unterkachel rechts im Kasten „Einordnung“ die übergeordnete Kachel wählen. Eine Kachel hat entweder Unterkacheln oder eine Wegbeschreibung – nie beides.',
                    'Reihenfolge: im Kasten „Einordnung“ das Feld „Reihenfolge“ (kleine Zahl = weiter vorne, z. B. 10, 20, 30). Höchstens 8 Kacheln je Ebene; mehr bitte in Untermenüs gliedern.',
                    'Englische Texte sind optional – fehlen sie, erscheint der deutsche Text.',
                    'Weg-Bewegtbild: animiertes WebP oder GIF (empfohlen) oder MP4; es startet sofort, stumm und in Schleife. Ohne Bewegtbild werden die Wegschritte größer dargestellt.',
                ],
            ],
            PostTypes::FULLPAGE => [
                'title' => 'Vollbild-Seiten – übernehmen den ganzen Bildschirm',
                'lead' => 'Eine Vollbild-Seite übernimmt im eingestellten Zeitfenster den ganzen Bildschirm der gewählten Displays – vor allem anderen: vor Gruß, Geschlossen-Seite, Wegweiser und Abfolge. Typisch: „Guten Morgen, liebes Team!“ Montag bis Freitag 06:55–07:10.',
                'points' => [
                    'Inhalt: Titel plus Text aus dem Editor (Überschriften, Absätze, Listen, Bilder). Logo, Datum und Uhrzeit stehen automatisch im Kopf.',
                    'Zeitregel „wiederkehrend“ (Wochentage + Uhrzeit) oder „einmalig“ (von – bis mit Datum).',
                    '„Nur an Öffnungstagen“: erscheint nicht an Feiertagen, an Tagen ohne Öffnungszeit und in Schließzeiten.',
                    'Besucher-Knopf (nur Empfangsdisplays): Besucher tippen „Besucher? Hier tippen“ und sehen, was ohne die Vollbild-Seite angezeigt würde. Nach dem Rücksprung erscheint die Vollbild-Seite wieder.',
                    'Überschneiden sich zwei Vollbild-Seiten, gewinnt die höhere Priorität, bei Gleichstand die zuletzt geänderte. Auf Vollbild-Seiten erscheinen keine Hinweis-Balken.',
                ],
            ],
            PostTypes::NEWS => [
                'title' => 'News – Meldungen für die Info-Displays',
                'lead' => 'News erscheinen nur auf Info-Displays (nicht am Empfang) der gewählten Standorte und nur im Gültigkeitszeitraum.',
                'points' => [
                    '„Als eigene Folie“: Die News erscheint auf der News-Folie der Abfolge mit Bild, Titel, Text und „gültig bis“. Mehrere News wechseln sich bei jedem Durchlauf ab.',
                    '„Im Laufband“: Der Titel läuft unten am Bildschirm durch.',
                    'Die News-Folie erscheint nur, wenn ein Administrator sie in die Abfolge des Displays aufgenommen hat. Ohne gültige News wird sie übersprungen.',
                ],
            ],
            PostTypes::PAGE => [
                'title' => 'Display-Seiten – eigene Inhaltsseiten für Info-Displays',
                'lead' => 'Display-Seiten sind Inhaltsseiten nur für die Displays, z. B. „Unfallfreie Tage“ oder „Termine im Dezember“. Sie erscheinen nicht im Intranet.',
                'points' => [
                    'Eine Display-Seite erscheint erst, wenn ein Administrator sie in die Abfolge eines Info-Displays bzw. einer Display-Kategorie aufnimmt – dort werden auch Dauer und Zeitfenster festgelegt.',
                    'Änderungen am Inhalt sind nach der nächsten Abfrage des Displays sichtbar (Standard 60 Sekunden).',
                ],
            ],
            PostTypes::DISPLAY => [
                'title' => 'Displays – je ein Eintrag pro Bildschirm',
                'lead' => 'Jeder Bildschirm ist ein Eintrag mit eindeutiger Kennung. Die Adresse für Slide Show steht beim Display rechts im Kasten „Adresse & Status“.',
                'points' => [
                    'Die Kategorie bestimmt den Typ (Empfang mit Touch oder Info ohne Touch) und das Standardverhalten. Abweichungen nur ausfüllen, wenn genau dieses Display anders arbeiten soll.',
                    'Der Standort liefert Logo, Wetter und Öffnungszeiten.',
                    'Info-Displays zeigen die Standard-Abfolge ihrer Kategorie oder eine eigene Abfolge.',
                    '„Zustand testen“ zeigt für einen beliebigen Zeitpunkt, was das Display anzeigen würde. „Zuletzt gemeldet“ verrät, ob das Gerät die Seite abruft.',
                ],
            ],
            PostTypes::CATEGORY => [
                'title' => 'Display-Kategorien – Einstellungen für gleichartige Displays',
                'lead' => 'Eine Kategorie bündelt die Einstellungen für alle Displays einer Art, z. B. „Empfang“ und „Info intern“. Eine Änderung hier wirkt sofort auf alle Displays der Kategorie – außer ein Display weicht beim jeweiligen Wert ab.',
                'points' => [
                    'Anzeige-Felder (Pause, Feierabend, Schließzeit): „Vollbild-Seite“, „Balken“ oder „nichts“.',
                    'Die Standard-Abfolge gilt für alle Info-Displays der Kategorie ohne eigene Abfolge.',
                    'Empfehlung: Einstellungen möglichst hier pflegen statt an einzelnen Displays.',
                ],
            ],
            PostTypes::LOCATION => [
                'title' => 'Standorte – Öffnungszeiten, Feiertage, Logo, Wetter',
                'lead' => 'Die Öffnungszeiten des Standorts steuern auf allen zugehörigen Displays Pause, Geschlossen-Seite, Vorwarnung und „Wir sind wieder für Sie da“.',
                'points' => [
                    'Je Wochentag einen oder mehrere Blöcke eintragen, z. B. „7-12, 13-17“. Leer = geschlossen.',
                    'Eine Lücke zwischen zwei Blöcken gilt als Pause (Lücken unter 15 Minuten werden ignoriert, einstellbar unter Einstellungen).',
                    'Feiertage werden nach dem gewählten Kalender (Österreich/Bayern) automatisch berechnet; einzelne Feiertage lassen sich als „trotzdem geöffnet“ markieren.',
                    'Ausnahmen gelten für einzelne Tage (geschlossen oder andere Zeiten) und können vorher angekündigt werden. Mehrere Tage am Stück → Schließzeit anlegen.',
                ],
            ],
            PostTypes::CLOSURE => [
                'title' => 'Schließzeiten – z. B. Betriebsurlaub',
                'lead' => 'Eine Schließzeit schließt einen oder mehrere Standorte über mehrere Tage. Sie überlagert die Öffnungszeiten.',
                'points' => [
                    'Vorher wird sie angekündigt (Standard 14 Tage): am Empfang als Balken, auf Info-Displays im Laufband und auf der News-Folie.',
                    'Während der Schließzeit zeigen Empfangsdisplays die Schließzeit-Seite mit Bezeichnung, Text, Bild und dem Tag der Wiedereröffnung; Info-Displays laufen normal weiter (einstellbar in der Kategorie).',
                    'Anschließende Wochenenden und Feiertage werden automatisch mitgerechnet: „Am Donnerstag, 7. Jänner, ab 07:00 Uhr wieder für Sie da“.',
                ],
            ],
        ];
    }

    public static function box(string $type, bool $compact): string
    {
        $t = self::texts()[$type] ?? null;
        if (!$t) {
            return '';
        }
        $points = '<ul>' . implode('', array_map(static fn ($p) => '<li>' . esc_html($p) . '</li>', $t['points'])) . '</ul>';
        if ($compact) {
            return '<details class="mms-help mms-help-compact"><summary><strong>So funktioniert es:</strong> ' . esc_html($t['lead']) . ' <span class="mms-help-more">mehr</span></summary>' . $points . '</details>';
        }
        return '<div class="mms-help"><h2>' . esc_html($t['title']) . '</h2><p>' . esc_html($t['lead']) . '</p>' . $points . '</div>';
    }

    public static function editBox(\WP_Post $post): void
    {
        if (isset(self::texts()[$post->post_type])) {
            echo self::box($post->post_type, true);
        }
    }
}
