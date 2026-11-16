<?php
namespace Marberger\Signage\WP\Admin;

use Marberger\Signage\Core\Holidays;
use Marberger\Signage\Core\OpeningCalendar;
use Marberger\Signage\Core\Texts;
use Marberger\Signage\Core\TimeBlocks;
use Marberger\Signage\WP\PostTypes;
use Marberger\Signage\WP\Store;

final class LocationBox extends Box
{
    protected function postType(): string
    {
        return PostTypes::LOCATION;
    }

    protected function title(): string
    {
        return 'Standort: Logo, Wetter, Öffnungszeiten';
    }

    public function render(\WP_Post $post): void
    {
        $d = PostTypes::data($post->ID);
        $cal = $d['calendar'] ?? 'AT';
        echo '<table class="form-table mms-form">';
        echo Fields::row('Logo', Fields::media('mms[logo]', (int) ($d['logo'] ?? 0), 'Logo wählen'), 'Erscheint nur auf den Displays dieses Standorts.');
        echo Fields::row('Koordinaten (Wetter)',
            Fields::text('mms[lat]', $d['lat'] ?? '', ['class' => 'mms-coord', 'placeholder' => '47.24']) . ' Breite &nbsp; '
            . Fields::text('mms[lon]', $d['lon'] ?? '', ['class' => 'mms-coord', 'placeholder' => '10.86']) . ' Länge',
            'Dezimalgrad, z. B. aus Google Maps (Rechtsklick auf den Ort).');
        echo Fields::row('Feiertagskalender', Fields::select('mms[calendar]', $cal, Holidays::calendars()));

        $rows = '';
        for ($i = 1; $i <= 7; $i++) {
            $raw = $d['weekly_raw'][$i] ?? TimeBlocks::format($d['weekly'][$i] ?? []);
            $rows .= '<tr><td class="mms-day">' . esc_html(Texts::WEEKDAYS_DE[$i]) . '</td><td>'
                . Fields::text("mms[weekly][$i]", str_replace('–', '-', $raw), ['placeholder' => 'geschlossen', 'class' => 'regular-text']) . '</td></tr>';
        }
        echo Fields::row('Öffnungszeiten', '<table class="mms-week">' . $rows . '</table>',
            'Pro Tag die Zeiten durch Beistrich getrennt, z. B. <code>07:00-12:00, 13:00-17:00</code> (Kurzform <code>7-12, 13-17</code> geht auch). Leer = geschlossen.');

        $open = $d['holiday_open'] ?? [];
        $boxes = '';
        foreach (Holidays::keys($cal) as $key => $name) {
            $boxes .= '<span class="mms-inline">' . Fields::checkbox('mms[holiday_open][]', in_array($key, $open, true), $name, $key) . '</span>';
        }
        echo Fields::row('Feiertage trotzdem geöffnet', $boxes, 'Angehakte Feiertage gelten als normaler Werktag laut Öffnungszeiten.');

        echo Fields::row('Ausnahmen (einzelne Tage)', $this->exceptionsTable($d['exceptions'] ?? []),
            'Z. B. „23.12. nur 07:00-12:00“ oder ein Fenstertag. Mehrtägige Schließungen bitte als <a href="' . esc_url(admin_url('edit.php?post_type=' . PostTypes::CLOSURE)) . '">Schließzeit</a> anlegen.');
        echo Fields::row('Vorschau Feiertage', $this->holidayPreview($post->ID));
        echo '</table>';
    }

    private function exceptionsTable(array $rows): string
    {
        $h = '<table class="widefat mms-repeater" data-next="' . count($rows) . '"><thead><tr><th>Datum</th><th>Art</th><th>Zeiten</th><th>Bezeichnung</th><th>Ankündigen (Tage vorher)</th><th></th></tr></thead><tbody>';
        foreach (array_values($rows) as $i => $r) {
            $h .= $this->exceptionRow((string) $i, $r);
        }
        $h .= '</tbody></table><template class="mms-row-template">' . $this->exceptionRow('__i__', []) . '</template>';
        return $h . '<p><button type="button" class="button mms-add-row">Ausnahme hinzufügen</button></p>';
    }

    private function exceptionRow(string $i, array $r): string
    {
        $n = "mms[exceptions][$i]";
        return '<tr><td>' . Fields::date($n . '[date]', $r['date'] ?? '') . '</td>'
            . '<td>' . Fields::select($n . '[mode]', !empty($r['closed']) || empty($r['blocks']) ? 'closed' : 'hours', ['closed' => 'geschlossen', 'hours' => 'abweichende Zeiten']) . '</td>'
            . '<td>' . Fields::text($n . '[times]', str_replace('–', '-', TimeBlocks::format($r['blocks'] ?? [])), ['class' => '', 'placeholder' => '07:00-12:00']) . '</td>'
            . '<td>' . Fields::text($n . '[label]', $r['label'] ?? '', ['class' => '', 'placeholder' => 'z. B. Inventur']) . '</td>'
            . '<td>' . Fields::number($n . '[announce_days]', $r['announce_days'] ?? 0, ['min' => 0, 'max' => 60]) . '</td>'
            . '<td><button type="button" class="button-link mms-remove-row" aria-label="Zeile entfernen">✕</button></td></tr>';
    }

    private function holidayPreview(int $postId): string
    {
        $cal = new OpeningCalendar(Store::calendarConfig($postId), Store::timezone());
        $today = new \DateTimeImmutable('today', Store::timezone());
        $d = PostTypes::data($postId);
        $calendar = $d['calendar'] ?? 'AT';
        $items = [];
        foreach ([(int) $today->format('Y'), (int) $today->format('Y') + 1] as $year) {
            foreach (Holidays::forYear($calendar, $year) as $date => $h) {
                if ($date < $today->format('Y-m-d') || count($items) >= 14) {
                    continue;
                }
                $day = new \DateTimeImmutable($date . ' 12:00', Store::timezone());
                $state = $cal->isOpenDay($day) ? '<span class="mms-ok">geöffnet</span>' : 'geschlossen';
                $items[] = '<li>' . esc_html($day->format('d.m.Y') . ' – ' . $h['de']) . ' · ' . $state . '</li>';
            }
        }
        return '<ul class="mms-list">' . implode('', $items) . '</ul><p class="description">Nach dem Speichern aktualisiert.</p>';
    }

    protected function sanitize(int $postId, array $in, array $old): array
    {
        $errors = [];
        $data = [
            'logo' => absint($in['logo'] ?? 0),
            'lat' => is_numeric($in['lat'] ?? null) ? (float) $in['lat'] : null,
            'lon' => is_numeric($in['lon'] ?? null) ? (float) $in['lon'] : null,
            'calendar' => array_key_exists($in['calendar'] ?? '', Holidays::calendars()) ? $in['calendar'] : 'AT',
            'weekly' => [],
            'weekly_raw' => [],
            'holiday_open' => array_values(array_map('sanitize_key', (array) ($in['holiday_open'] ?? []))),
            'exceptions' => [],
        ];
        for ($i = 1; $i <= 7; $i++) {
            $raw = sanitize_text_field((string) ($in['weekly'][$i] ?? ''));
            $parsed = TimeBlocks::parse($raw);
            $data['weekly_raw'][$i] = $raw;
            if ($parsed['errors']) {
                foreach ($parsed['errors'] as $e) {
                    $errors[] = Texts::WEEKDAYS_DE[$i] . ': ' . $e . ' Die bisherigen Zeiten bleiben aktiv.';
                }
                $data['weekly'][$i] = $old['weekly'][$i] ?? [];
            } else {
                $data['weekly'][$i] = $parsed['blocks'];
            }
        }
        foreach ((array) ($in['exceptions'] ?? []) as $row) {
            $date = self::date($row, 'date');
            if ($date === '') {
                if (!empty($row['times']) || !empty($row['label'])) {
                    $errors[] = 'Ausnahme ohne gültiges Datum wurde nicht gespeichert.';
                }
                continue;
            }
            $ex = ['date' => $date, 'closed' => ($row['mode'] ?? 'closed') === 'closed', 'blocks' => [],
                'label' => self::str($row, 'label', 80), 'announce_days' => min(60, absint($row['announce_days'] ?? 0))];
            if (!$ex['closed']) {
                $parsed = TimeBlocks::parse((string) ($row['times'] ?? ''));
                if ($parsed['errors'] || !$parsed['blocks']) {
                    $errors[] = sprintf('Ausnahme %s: %s Die Zeile wurde nicht gespeichert.', $date, $parsed['errors'][0] ?? 'Bitte Zeiten angeben.');
                    continue;
                }
                $ex['blocks'] = $parsed['blocks'];
            }
            $data['exceptions'][] = $ex;
        }
        usort($data['exceptions'], static fn ($a, $b) => strcmp($a['date'], $b['date']));
        PostTypes::save($postId, $data);
        return $errors;
    }
}
