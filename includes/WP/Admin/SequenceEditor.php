<?php
namespace Marberger\Signage\WP\Admin;

use Marberger\Signage\Core\Texts;
use Marberger\Signage\WP\PostTypes;
use Marberger\Signage\WP\Sequence;

/** Wiederholgruppe für die Abfolge eines Info-Displays (Kategorie oder Display). */
final class SequenceEditor
{
    public static function render(string $name, array $rows): string
    {
        $h = '<div class="mms-rep-wrap"><table class="widefat mms-repeater mms-seq" data-next="' . count($rows) . '"><thead><tr>'
            . '<th>Typ</th><th>Titel (Kopfzeile)</th><th>Adresse / Seite</th><th>Dauer (s)</th><th>Nur zu bestimmten Zeiten</th><th>Design-Auflösung</th><th></th></tr></thead><tbody>';
        foreach (array_values($rows) as $i => $r) {
            $h .= self::row($name, (string) $i, $r);
        }
        $h .= '</tbody></table><template class="mms-row-template">' . self::row($name, '__i__', []) . '</template>';
        return $h . '<p><button type="button" class="button mms-add-row">Eintrag hinzufügen</button> <span class="description">Reihenfolge = Reihenfolge der Zeilen (mit ▲▼ verschieben).</span></p></div>';
    }

    private static function row(string $name, string $i, array $r): string
    {
        $n = $name . '[' . $i . ']';
        $pages = ['' => '– Display-Seite wählen –'] + PostTypes::choices(PostTypes::PAGE);
        $days = '';
        foreach (Texts::WEEKDAYS_DE as $d => $label) {
            $days .= '<label class="mms-day-mini"><input type="checkbox" name="' . esc_attr($n) . '[days][]" value="' . $d . '"' . checked(in_array($d, $r['days'] ?? [], true), true, false) . '>' . esc_html(mb_substr($label, 0, 2)) . '</label>';
        }
        return '<tr>'
            . '<td>' . Fields::select($n . '[type]', $r['type'] ?? 'url', Sequence::TYPES, ['class' => 'mms-seq-type']) . '</td>'
            . '<td>' . Fields::text($n . '[title]', $r['title'] ?? '', ['class' => '', 'placeholder' => 'z. B. Zuschnitt']) . '</td>'
            . '<td>' . Fields::text($n . '[url]', $r['url'] ?? '', ['class' => 'mms-seq-url', 'placeholder' => 'http://grafana…/d/…?kiosk']) . Fields::select($n . '[page]', $r['page'] ?? '', $pages, ['class' => 'mms-seq-page']) . '<span class="mms-seq-news description">zeigt die gültigen News des Standorts</span></td>'
            . '<td>' . Fields::number($n . '[duration]', ($r['duration'] ?? 0) ?: '', ['min' => 0, 'placeholder' => 'Std.']) . '</td>'
            . '<td><span class="mms-days">' . $days . '</span><input type="time" name="' . esc_attr($n) . '[from]" value="' . esc_attr($r['from'] ?? '') . '"> – <input type="time" name="' . esc_attr($n) . '[to]" value="' . esc_attr($r['to'] ?? '') . '"></td>'
            . '<td class="mms-seq-res">' . Fields::number($n . '[width]', $r['width'] ?? 1920, ['min' => 320]) . ' × ' . Fields::number($n . '[height]', $r['height'] ?? 1080, ['min' => 240]) . '</td>'
            . '<td class="mms-row-tools"><button type="button" class="button-link mms-up" aria-label="nach oben">▲</button><button type="button" class="button-link mms-down" aria-label="nach unten">▼</button><button type="button" class="button-link mms-remove-row" aria-label="Zeile entfernen">✕</button></td>'
            . '</tr>';
    }
}
