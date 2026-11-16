<?php
namespace Marberger\Signage\WP\Admin;

use Marberger\Signage\WP\Editorial;
use Marberger\Signage\WP\PostTypes;
use Marberger\Signage\WP\Templates;

/** Gemeinsame Speicherlogik: Daten immer speichern, bei Fehlern als ungültig markieren (wird nicht angezeigt). */
abstract class EditorialBox extends Box
{
    protected function store(int $postId, array $res, array $extra = []): array
    {
        $data = $res['data'] + $extra;
        $data['valid'] = !$res['errors'];
        PostTypes::save($postId, $data);
        if ($res['errors']) {
            $res['errors'][] = 'Solange diese Punkte offen sind, wird der Eintrag nicht angezeigt.';
        }
        return $res['errors'];
    }

    protected static function split(string $dt): array
    {
        return $dt !== '' ? explode(' ', $dt) + ['', ''] : ['', ''];
    }

    /** Nächster Tag ab heute (frühestens $minDate), dessen Wochentag (1 = Mo) in $days liegt, als 'Y-m-d'. */
    protected static function nextDay(array $days, string $minDate = ''): string
    {
        $d = new \DateTimeImmutable('today', \Marberger\Signage\WP\Store::timezone());
        if ($minDate !== '' && $minDate > $d->format('Y-m-d')) {
            $d = new \DateTimeImmutable($minDate, \Marberger\Signage\WP\Store::timezone());
        }
        for ($i = 0; $i < 7; $i++) {
            if (!$days || in_array((int) $d->format('N'), array_map('intval', $days), true)) {
                return $d->format('Y-m-d');
            }
            $d = $d->modify('+1 day');
        }
        return $d->format('Y-m-d');
    }

    /** Zeile mit Links „So sieht es am Display aus“ zum Zeitpunkt $at (Y-m-d H:i). */
    protected static function previewRow(\WP_Post $post, array $data, string $at): string
    {
        if ($post->post_status === 'auto-draft' || empty($data['valid']) || $at === '' || empty($data['displays'])) {
            return '';
        }
        $links = [];
        foreach ($data['displays'] as $id) {
            $key = (string) get_post_meta((int) $id, \Marberger\Signage\WP\Store::KEY_META, true);
            if ($key !== '') {
                $url = add_query_arg('at', str_replace(' ', 'T', $at), \Marberger\Signage\WP\Player::url($key));
                $links[] = '<a class="button" target="_blank" href="' . esc_url($url) . '">' . esc_html(get_the_title((int) $id)) . '</a>';
            }
        }
        if (!$links) {
            return '';
        }
        $label = wp_date('D, d.m.Y H:i', (new \DateTimeImmutable($at, \Marberger\Signage\WP\Store::timezone()))->getTimestamp());
        return Fields::row('Vorschau', implode(' ', $links), 'Öffnet das Display, wie es am ' . $label . ' Uhr aussieht (nach dem Speichern).');
    }
}

final class GreetingBox extends EditorialBox
{
    protected function postType(): string { return PostTypes::GREETING; }
    protected function title(): string { return 'Gruß'; }

    public function render(\WP_Post $post): void
    {
        $d = PostTypes::data($post->ID);
        $now = new \DateTimeImmutable('now', \Marberger\Signage\WP\Store::timezone());
        [$fd, $ft] = self::split($d['from'] ?? '');
        [$td, $tt] = self::split($d['to'] ?? '');
        if (!$d) {
            [$fd, $ft, $td, $tt] = [$now->format('Y-m-d'), $now->format('H') . ':00', $now->format('Y-m-d'), min(23, (int) $now->format('H') + 3) . ':00'];
            $ft = str_pad($ft, 5, '0', STR_PAD_LEFT);
            $tt = str_pad($tt, 5, '0', STR_PAD_LEFT);
        }
        $tpl = ['' => '– Vorlage wählen –'];
        foreach (Templates::all() as $i => $t) {
            $tpl[$i] = $t['label'];
        }
        echo '<p class="description">Oben im Titelfeld: <strong>Name / Gruppe</strong>, z. B. „HTL Imst – Klasse 3B“.</p><table class="form-table mms-form">';
        echo Fields::row('Schnellvorlage', Fields::select('mms_tpl', '', $tpl, ['class' => 'mms-template', 'data-templates' => wp_json_encode(Templates::all())]), 'Füllt die Texte vor. Platzhalter in [eckigen Klammern] bitte ersetzen.');
        echo Fields::row('Gast-Logo', Fields::media('mms[logo]', (int) ($d['logo'] ?? 0), 'Logo wählen'));
        echo Fields::row('Begrüßungszeile', Fields::text('mms[kicker_de]', $d['kicker_de'] ?? 'Herzlich willkommen'));
        echo Fields::row('Zusatzzeile', Fields::text('mms[sub_de]', $d['sub_de'] ?? '', ['class' => 'large-text']));
        echo Fields::row('Begrüßungszeile (Englisch)', Fields::text('mms[kicker_en]', $d['kicker_en'] ?? 'Welcome'));
        echo Fields::row('Zusatzzeile (Englisch)', Fields::text('mms[sub_en]', $d['sub_en'] ?? '', ['class' => 'large-text']));
        echo Fields::row('Von', Fields::date('mms[from_date]', $fd) . ' ' . '<input type="time" name="mms[from_time]" value="' . esc_attr($ft) . '">');
        echo Fields::row('Bis', Fields::date('mms[to_date]', $td) . ' ' . '<input type="time" name="mms[to_time]" value="' . esc_attr($tt) . '">');
        echo Fields::row('Mehrere Tage', Fields::checkbox('mms[daily]', !empty($d['daily']), 'nur täglich von Start- bis Endzeit'));
        echo Fields::row('Anzeigen auf', self::displayChecks('mms[displays]', $d['displays'] ?? array_keys(PostTypes::displays('reception')), 'reception'));
        echo self::previewRow($post, $d, $d['from'] ?? '');
        echo '</table>';
    }

    protected function sanitize(int $postId, array $in, array $old): array
    {
        $in['name'] = self::postTitle();
        return $this->store($postId, Editorial::greeting($in));
    }
}

final class NoticeBox extends EditorialBox
{
    protected function postType(): string { return PostTypes::NOTICE; }
    protected function title(): string { return 'Hinweis'; }

    public function render(\WP_Post $post): void
    {
        $d = PostTypes::data($post->ID) + ['days' => [5], 'from' => '12:00', 'to' => 'close', 'active' => true, 'icon' => 'right', 'priority' => 0];
        echo '<p class="description">Oben im Titelfeld: <strong>Text des Hinweises (Deutsch)</strong>, z. B. „Freitagnachmittag: Eingang rechts über die Haupteinfahrt“.</p><table class="form-table mms-form">';
        echo Fields::row('Text (Englisch)', Fields::text('mms[text_en]', $d['text_en'] ?? '', ['class' => 'large-text']));
        echo Fields::row('Symbol', Fields::select('mms[icon]', $d['icon'], Editorial::NOTICE_ICONS));
        echo Fields::row('Wochentage', self::dayChecks('mms[days]', $d['days']));
        echo Fields::row('Von', '<input type="time" name="mms[from]" value="' . esc_attr($d['from']) . '">');
        echo Fields::row('Bis', '<input type="time" name="mms[to]" value="' . esc_attr($d['to'] === 'close' ? '' : $d['to']) . '"> &nbsp; ' . Fields::checkbox('mms[until_close]', $d['to'] === 'close', 'bis Geschäftsschluss (laut Öffnungszeiten)'));
        echo Fields::row('Gültig (optional)', Fields::date('mms[valid_from]', $d['valid_from'] ?? '') . ' bis ' . Fields::date('mms[valid_to]', $d['valid_to'] ?? ''), 'Z. B. nur während einer Baustelle.');
        echo Fields::row('Anzeigen auf', self::displayChecks('mms[displays]', $d['displays'] ?? []));
        echo Fields::row('Priorität', Fields::number('mms[priority]', $d['priority'], ['min' => 0, 'max' => 99]), 'Höher = weiter oben. Es werden höchstens 3 Balken gleichzeitig gezeigt.');
        echo Fields::row('Aktiv', Fields::checkbox('mms[active]', !empty($d['active']), 'Hinweis ist aktiv'));
        if (($d['from'] ?? '') !== '' && !empty($d['active'])) {
            echo self::previewRow($post, $d, self::nextDay($d['days'], $d['valid_from'] ?? '') . ' ' . $d['from']);
        }
        echo '</table>';
    }

    protected function sanitize(int $postId, array $in, array $old): array
    {
        $in['text_de'] = self::postTitle();
        return $this->store($postId, Editorial::notice($in));
    }
}

final class FullpageBox extends EditorialBox
{
    protected function postType(): string { return PostTypes::FULLPAGE; }
    protected function title(): string { return 'Wann und wo'; }

    public function render(\WP_Post $post): void
    {
        $d = PostTypes::data($post->ID) + ['rule_type' => 'weekly', 'days' => [1, 2, 3, 4, 5], 'from' => '06:55', 'to' => '07:10', 'open_days_only' => true, 'visitor_button' => true, 'priority' => 0];
        [$ofd, $oft] = self::split($d['once_from'] ?? '');
        [$otd, $ott] = self::split($d['once_to'] ?? '');
        echo '<p class="description">Der Inhalt oben (Texteditor mit Bild) füllt den ganzen Bildschirm.</p><table class="form-table mms-form">';
        echo Fields::row('Zeitregel', Fields::select('mms[rule_type]', $d['rule_type'], ['weekly' => 'wiederkehrend (Wochentage)', 'once' => 'einmalig (von – bis)'], ['class' => 'mms-ruletype']));
        echo '<tbody class="mms-rule mms-rule-weekly">';
        echo Fields::row('Wochentage', self::dayChecks('mms[days]', $d['days']));
        echo Fields::row('Uhrzeit', '<input type="time" name="mms[from]" value="' . esc_attr($d['from']) . '"> bis <input type="time" name="mms[to]" value="' . esc_attr($d['to']) . '">');
        echo Fields::row('Nur an Öffnungstagen', Fields::checkbox('mms[open_days_only]', !empty($d['open_days_only']), 'nicht an Feiertagen, Wochenenden ohne Öffnung und in Schließzeiten'));
        echo '</tbody><tbody class="mms-rule mms-rule-once">';
        echo Fields::row('Von', Fields::date('mms[once_from_date]', $ofd) . ' <input type="time" name="mms[once_from_time]" value="' . esc_attr($oft) . '">');
        echo Fields::row('Bis', Fields::date('mms[once_to_date]', $otd) . ' <input type="time" name="mms[once_to_time]" value="' . esc_attr($ott) . '">');
        echo '</tbody><tbody>';
        echo Fields::row('Anzeigen auf', self::displayChecks('mms[displays]', $d['displays'] ?? []));
        echo Fields::row('Besucher-Knopf', Fields::checkbox('mms[visitor_button]', !empty($d['visitor_button']), '„Besucher? Hier tippen“ zeigen (nur Empfangsdisplays)'));
        echo Fields::row('Priorität', Fields::number('mms[priority]', $d['priority'], ['min' => 0, 'max' => 99]), 'Bei Überschneidung gewinnt die höhere Priorität.');
        $at = $d['rule_type'] === 'once' ? ($d['once_from'] ?? '') : self::nextDay($d['days']) . ' ' . $d['from'];
        echo self::previewRow($post, $d, $at);
        echo '</tbody></table>';
    }

    protected function sanitize(int $postId, array $in, array $old): array
    {
        return $this->store($postId, Editorial::fullpage($in));
    }
}

final class NewsBox extends EditorialBox
{
    protected function postType(): string { return PostTypes::NEWS; }
    protected function title(): string { return 'News'; }

    public function render(\WP_Post $post): void
    {
        $today = (new \DateTimeImmutable('now', \Marberger\Signage\WP\Store::timezone()));
        $d = PostTypes::data($post->ID) + ['from' => $today->format('Y-m-d'), 'to' => $today->modify('+7 days')->format('Y-m-d'), 'slide' => true, 'ticker' => true];
        $sites = '';
        foreach (PostTypes::choices(PostTypes::LOCATION) as $id => $name) {
            $sites .= '<span class="mms-inline">' . Fields::checkbox('mms[sites][]', in_array($id, array_map('intval', $d['sites'] ?? []), true), $name, (string) $id) . '</span>';
        }
        echo '<p class="description">Oben im Titelfeld: <strong>Titel der News</strong> (erscheint auch im Laufband).</p><table class="form-table mms-form">';
        echo Fields::row('Text', Fields::textarea('mms[text]', $d['text'] ?? '', ['rows' => 4]), 'Erscheint auf der News-Folie.');
        echo Fields::row('Bild', Fields::media('mms[image]', (int) ($d['image'] ?? 0)));
        echo Fields::row('Gültig', Fields::date('mms[from]', $d['from']) . ' bis ' . Fields::date('mms[to]', $d['to']));
        echo Fields::row('Standorte', $sites ?: '<em>Noch keine Standorte angelegt.</em>');
        echo Fields::row('Anzeige', Fields::checkbox('mms[slide]', !empty($d['slide']), 'als eigene Folie') . ' &nbsp; ' . Fields::checkbox('mms[ticker]', !empty($d['ticker']), 'im Laufband'));
        echo '</table>';
    }

    protected function sanitize(int $postId, array $in, array $old): array
    {
        $in['title'] = self::postTitle();
        return $this->store($postId, Editorial::news($in));
    }
}

final class NodeBox extends EditorialBox
{
    protected function postType(): string { return PostTypes::NODE; }
    protected function title(): string { return 'Kachel und Detailseite'; }

    public function render(\WP_Post $post): void
    {
        $d = PostTypes::data($post->ID);
        $children = get_posts(['post_type' => PostTypes::NODE, 'post_parent' => $post->ID, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids']);
        $siblings = $post->ID ? count(get_posts(['post_type' => PostTypes::NODE, 'post_parent' => $post->post_parent, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids'])) : 0;
        echo '<p class="description">Oben im Titelfeld: <strong>Titel der Kachel (Deutsch)</strong>. Übergeordnete Kachel (für Untermenüs) und Reihenfolge rechts im Kasten „Einordnung“.</p>';
        if ($children) {
            echo '<div class="notice notice-info inline"><p>Diese Kachel hat ' . count($children) . ' Unterkacheln – sie öffnet daher eine Unterseite mit Kacheln. Die Felder der Detailseite werden nicht angezeigt.</p></div>';
        }
        if ($siblings > 8) {
            echo '<div class="notice notice-warning inline"><p>Auf dieser Ebene liegen ' . $siblings . ' Kacheln. Angezeigt werden höchstens 8 – bitte ein Untermenü verwenden.</p></div>';
        }
        echo '<table class="form-table mms-form">';
        echo Fields::row('Titel (Englisch)', Fields::text('mms[title_en]', $d['title_en'] ?? ''));
        echo Fields::row('Untertitel', Fields::text('mms[sub_de]', $d['sub_de'] ?? '', ['placeholder' => 'z. B. Holzbau, Platten, Bauholz']));
        echo Fields::row('Untertitel (Englisch)', Fields::text('mms[sub_en]', $d['sub_en'] ?? ''));
        echo Fields::row('Symbol', Fields::select('mms[icon]', $d['icon'] ?? 'info', Editorial::ICONS));
        echo '</table><h3 class="mms-sub">Detailseite</h3><table class="form-table mms-form">';
        echo Fields::row('Weg-Bewegtbild', Fields::media('mms[media]', (int) ($d['media'] ?? 0), 'Datei wählen'), 'Optional: animiertes WebP oder GIF (empfohlen) oder MP4, 5–8 Bilder/s, max. 1080 px breit. Ohne Bewegtbild werden die Wegschritte größer dargestellt.');
        echo Fields::row('Wegschritte', Fields::textarea('mms[steps_de]', implode("\n", $d['steps_de'] ?? []), ['rows' => 4, 'placeholder' => "Durch die Glastür geradeaus\nLinks abbiegen\n2. Tür links"]), 'Ein Schritt pro Zeile.');
        echo Fields::row('Wegschritte (Englisch)', Fields::textarea('mms[steps_en]', implode("\n", $d['steps_en'] ?? []), ['rows' => 4]));
        echo Fields::row('Ansprechpartner', Fields::text('mms[contact]', $d['contact'] ?? '', ['placeholder' => 'z. B. Team Verkauf konstruktiv']) . ' &nbsp; DW ' . Fields::text('mms[ext]', $d['ext'] ?? '', ['class' => 'small-text']));
        echo Fields::row('Foto Ansprechpartner', Fields::media('mms[photo]', (int) ($d['photo'] ?? 0)), 'Optional – nur mit Einwilligung der abgebildeten Person.');
        echo Fields::row('QR-Code-Ziel', Fields::text('mms[qr_url]', $d['qr_url'] ?? '', ['class' => 'large-text', 'placeholder' => 'https://www.holz-marberger.at/…']), 'Optional: Seite auf der öffentlichen Website („Weg aufs Handy“).');
        echo '</table>';
    }

    protected function sanitize(int $postId, array $in, array $old): array
    {
        return $this->store($postId, Editorial::node($in));
    }
}

final class PageBox extends EditorialBox
{
    protected function postType(): string { return PostTypes::PAGE; }
    protected function title(): string { return 'Display-Seite'; }

    public function render(\WP_Post $post): void
    {
        $d = PostTypes::data($post->ID);
        echo '<p class="description">Inhalt oben im Editor (Überschriften, Text, Listen, Bilder). Die Seite wird in der Abfolge eines Info-Displays ausgewählt; der Titel erscheint in dessen Kopfzeile.</p><table class="form-table mms-form">';
        echo Fields::row('Format', Fields::select('mms[format]', $d['format'] ?? 'quer', ['quer' => 'Querformat', 'hoch' => 'Hochformat']));
        echo '</table>';
    }

    protected function sanitize(int $postId, array $in, array $old): array
    {
        PostTypes::save($postId, ['format' => ($in['format'] ?? '') === 'hoch' ? 'hoch' : 'quer', 'valid' => true]);
        return [];
    }
}
