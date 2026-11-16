<?php
namespace Marberger\Signage\WP\Admin;

use Marberger\Signage\WP\PostTypes;

final class ClosureBox extends Box
{
    protected function postType(): string
    {
        return PostTypes::CLOSURE;
    }

    protected function title(): string
    {
        return 'Schließzeit (z. B. Betriebsurlaub)';
    }

    public function render(\WP_Post $post): void
    {
        $d = PostTypes::data($post->ID);
        $locs = '';
        foreach (PostTypes::choices(PostTypes::LOCATION) as $id => $name) {
            $locs .= '<span class="mms-inline">' . Fields::checkbox('mms[locations][]', in_array($id, array_map('intval', $d['locations'] ?? []), true), $name, (string) $id) . '</span>';
        }
        if ($locs === '') {
            $locs = '<em>Noch keine Standorte angelegt.</em>';
        }
        echo '<table class="form-table mms-form">';
        echo Fields::row('Bezeichnung (Deutsch)', Fields::text('mms[label_de]', $d['label_de'] ?? 'Betriebsurlaub'));
        echo Fields::row('Bezeichnung (Englisch)', Fields::text('mms[label_en]', $d['label_en'] ?? 'Company holidays'));
        echo Fields::row('Zeitraum', Fields::date('mms[from]', $d['from'] ?? '') . ' bis ' . Fields::date('mms[to]', $d['to'] ?? ''), 'Erster und letzter geschlossener Tag. Angrenzende Wochenenden und Feiertage werden automatisch mitberücksichtigt.');
        echo Fields::row('Standorte', $locs, 'Weichen die Zeiten an einem Standort ab, dafür eine eigene Schließzeit anlegen.');
        echo Fields::row('Text (Deutsch)', Fields::textarea('mms[text_de]', $d['text_de'] ?? '', ['placeholder' => 'z. B. Wir wünschen frohe Weihnachten und einen guten Rutsch!']), 'Optional, erscheint auf der Schließzeit-Seite.');
        echo Fields::row('Text (Englisch)', Fields::textarea('mms[text_en]', $d['text_en'] ?? ''));
        echo Fields::row('Bild', Fields::media('mms[image]', (int) ($d['image'] ?? 0)), 'Optional.');
        echo Fields::row('Vorankündigung', Fields::number('mms[announce_days]', $d['announce_days'] ?? 14, ['min' => 0, 'max' => 90]) . ' Tage vorher', '0 = keine Vorankündigung.');
        echo '</table>';
    }

    protected function sanitize(int $postId, array $in, array $old): array
    {
        $errors = [];
        $data = [
            'label_de' => self::str($in, 'label_de', 80) ?: 'Betriebsurlaub',
            'label_en' => self::str($in, 'label_en', 80),
            'from' => self::date($in, 'from'),
            'to' => self::date($in, 'to'),
            'locations' => array_values(array_map('absint', (array) ($in['locations'] ?? []))),
            'text_de' => sanitize_textarea_field((string) ($in['text_de'] ?? '')),
            'text_en' => sanitize_textarea_field((string) ($in['text_en'] ?? '')),
            'image' => absint($in['image'] ?? 0),
            'announce_days' => min(90, absint($in['announce_days'] ?? 14)),
        ];
        if ($data['from'] === '' || $data['to'] === '') {
            $errors[] = 'Bitte Beginn und Ende der Schließzeit angeben – sie ist sonst nicht wirksam.';
        } elseif ($data['to'] < $data['from']) {
            $errors[] = 'Das Ende der Schließzeit liegt vor dem Beginn – die Daten wurden getauscht.';
            [$data['from'], $data['to']] = [$data['to'], $data['from']];
        }
        if (!$data['locations']) {
            $errors[] = 'Die Schließzeit ist keinem Standort zugeordnet und daher nicht wirksam.';
        }
        PostTypes::save($postId, $data);
        return $errors;
    }
}
