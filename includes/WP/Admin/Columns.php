<?php
namespace Marberger\Signage\WP\Admin;

use Marberger\Signage\Core\Texts;
use Marberger\Signage\Core\TimeBlocks;
use Marberger\Signage\WP\Editorial;
use Marberger\Signage\WP\PostTypes;
use Marberger\Signage\WP\Status;
use Marberger\Signage\WP\Store;

/** Aussagekräftige Spalten in den Listen statt „Datum“. */
final class Columns
{
    private const COLS = [
        PostTypes::GREETING => ['mms_when' => 'Zeitraum', 'mms_displays' => 'Anzeigen auf', 'mms_status' => 'Status'],
        PostTypes::NOTICE => ['mms_when' => 'Wann', 'mms_displays' => 'Anzeigen auf', 'mms_prio' => 'Priorität', 'mms_status' => 'Status'],
        PostTypes::NODE => ['mms_icon' => 'Symbol', 'mms_sub' => 'Untertitel', 'mms_kind' => 'Inhalt', 'mms_order' => 'Reihenfolge'],
        PostTypes::FULLPAGE => ['mms_when' => 'Zeitregel', 'mms_displays' => 'Anzeigen auf', 'mms_prio' => 'Priorität', 'mms_status' => 'Status'],
        PostTypes::NEWS => ['mms_when' => 'Gültig', 'mms_sites' => 'Standorte', 'mms_where' => 'Anzeige', 'mms_status' => 'Status'],
        PostTypes::PAGE => ['mms_format' => 'Format', 'mms_used' => 'Verwendet in Abfolge'],
        PostTypes::DISPLAY => ['mms_key' => 'Kennung', 'mms_cat' => 'Kategorie', 'mms_loc' => 'Standort', 'mms_format' => 'Format', 'mms_seen' => 'Zuletzt gemeldet'],
        PostTypes::CATEGORY => ['mms_type' => 'Typ', 'mms_count' => 'Displays'],
        PostTypes::LOCATION => ['mms_hours' => 'Öffnungszeiten', 'mms_cal' => 'Feiertage'],
        PostTypes::CLOSURE => ['mms_when' => 'Zeitraum', 'mms_sites' => 'Standorte'],
    ];

    public static function init(): void
    {
        foreach (array_keys(self::COLS) as $type) {
            add_filter('manage_' . $type . '_posts_columns', static function (array $cols) use ($type): array {
                unset($cols['date']);
                return $cols + self::COLS[$type];
            });
            add_action('manage_' . $type . '_posts_custom_column', static function (string $col, int $id) use ($type): void {
                echo self::cell($type, $col, $id); // phpcs:ignore -- Werte werden in cell() maskiert
            }, 10, 2);
        }
    }

    private static function d(string $date): string
    {
        if ($date === '') {
            return '–';
        }
        $p = explode(' ', $date);
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $p[0]);
        return ($dt ? $dt->format('d.m.Y') : $p[0]) . (isset($p[1]) ? ' ' . $p[1] : '');
    }

    private static function days(array $days): string
    {
        $days = array_map('intval', $days);
        sort($days);
        if ($days === [1, 2, 3, 4, 5]) {
            return 'Mo–Fr';
        }
        if ($days === [1, 2, 3, 4, 5, 6, 7]) {
            return 'täglich';
        }
        return implode(', ', array_map(static fn ($d) => mb_substr(Texts::WEEKDAYS_DE[$d] ?? '', 0, 2), $days));
    }

    private static function titles(array $ids): string
    {
        $t = [];
        foreach ($ids as $id) {
            $title = get_the_title((int) $id);
            if ($title !== '') {
                $t[] = esc_html($title);
            }
        }
        return $t ? implode('<br>', $t) : '<span class="mms-muted">–</span>';
    }

    private static function cell(string $type, string $col, int $id): string
    {
        $d = PostTypes::data($id);
        switch ($col) {
            case 'mms_status':
                return Status::chip(Status::of($type, $d));
            case 'mms_displays':
                return self::titles($d['displays'] ?? []);
            case 'mms_sites':
                return self::titles($d[$type === PostTypes::CLOSURE ? 'locations' : 'sites'] ?? []);
            case 'mms_prio':
                return esc_html((string) (int) ($d['priority'] ?? 0));
            case 'mms_when':
                if ($type === PostTypes::GREETING) {
                    return esc_html(self::d($d['from'] ?? '') . ' – ' . self::d($d['to'] ?? '')) . (!empty($d['daily']) ? '<br><span class="mms-muted">täglich im Zeitfenster</span>' : '');
                }
                if ($type === PostTypes::NOTICE) {
                    $to = ($d['to'] ?? '') === 'close' ? 'Geschäftsschluss' : ($d['to'] ?? '');
                    $valid = ($d['valid_from'] ?? '') !== '' || ($d['valid_to'] ?? '') !== '' ? '<br><span class="mms-muted">gültig ' . esc_html(self::d($d['valid_from'] ?? '') . ' – ' . self::d($d['valid_to'] ?? '')) . '</span>' : '';
                    return esc_html(self::days($d['days'] ?? []) . ' ' . ($d['from'] ?? '') . ' – ' . $to) . $valid;
                }
                if ($type === PostTypes::FULLPAGE) {
                    if (($d['rule_type'] ?? '') === 'once') {
                        return esc_html(self::d($d['once_from'] ?? '') . ' – ' . self::d($d['once_to'] ?? ''));
                    }
                    return esc_html(self::days($d['days'] ?? []) . ' ' . ($d['from'] ?? '') . ' – ' . ($d['to'] ?? '')) . (!empty($d['open_days_only']) ? '<br><span class="mms-muted">nur an Öffnungstagen</span>' : '');
                }
                return esc_html(self::d($d['from'] ?? '') . ' – ' . self::d($d['to'] ?? ''));
            case 'mms_where':
                return esc_html(implode(' + ', array_filter([!empty($d['slide']) ? 'Folie' : '', !empty($d['ticker']) ? 'Laufband' : ''])) ?: '–');
            case 'mms_icon':
                return esc_html(Editorial::ICONS[$d['icon'] ?? 'info'] ?? '');
            case 'mms_sub':
                return esc_html($d['sub_de'] ?? '');
            case 'mms_kind':
                $children = get_children(['post_parent' => $id, 'post_type' => PostTypes::NODE, 'post_status' => 'publish', 'fields' => 'ids']);
                if ($children) {
                    return esc_html(count($children) . ' Unterkachel' . (count($children) === 1 ? '' : 'n'));
                }
                $parts = array_filter([!empty($d['media']) ? 'Bewegtbild' : '', !empty($d['steps_de']) ? count($d['steps_de']) . ' Wegschritte' : '', !empty($d['contact']) ? 'Ansprechpartner' : '', !empty($d['qr_url']) ? 'QR-Code' : '']);
                return $parts ? esc_html(implode(', ', $parts)) : '<span class="mms-warn-text">noch ohne Wegbeschreibung</span>';
            case 'mms_order':
                return esc_html((string) get_post_field('menu_order', $id));
            case 'mms_format':
                return ($d['format'] ?? ($type === PostTypes::PAGE ? 'quer' : 'hoch')) === 'quer' ? 'Querformat' : 'Hochformat';
            case 'mms_used':
                return self::usedIn($id);
            case 'mms_key':
                return '<code>' . esc_html((string) get_post_meta($id, Store::KEY_META, true)) . '</code>';
            case 'mms_cat':
                return self::titles(array_filter([$d['category'] ?? 0]));
            case 'mms_loc':
                return self::titles(array_filter([$d['location'] ?? 0]));
            case 'mms_seen':
                $seen = Store::lastSeen($id);
                return '<span class="mms-dot ' . ($seen && time() - $seen < 300 ? 'on' : 'off') . '"></span>' . esc_html(Overview::seenLabel($seen));
            case 'mms_type':
                return ($d['type'] ?? 'reception') === 'info' ? 'Info (ohne Touch)' : 'Empfang (Touch)';
            case 'mms_count':
                $n = 0;
                foreach (PostTypes::choices(PostTypes::DISPLAY) as $did => $t) {
                    if ((int) (PostTypes::data($did)['category'] ?? 0) === $id) {
                        $n++;
                    }
                }
                return esc_html((string) $n);
            case 'mms_hours':
                $rows = [];
                $week = $d['weekly'] ?? [];
                $blocks = [];
                for ($i = 1; $i <= 7; $i++) {
                    $blocks[$i] = TimeBlocks::format($week[$i] ?? []);
                }
                $start = 1;
                for ($i = 2; $i <= 8; $i++) {
                    if ($i === 8 || $blocks[$i] !== $blocks[$start]) {
                        $label = mb_substr(Texts::WEEKDAYS_DE[$start], 0, 2) . ($i - 1 > $start ? '–' . mb_substr(Texts::WEEKDAYS_DE[$i - 1], 0, 2) : '');
                        $rows[] = esc_html($label . ': ' . ($blocks[$start] !== '' ? $blocks[$start] : 'geschlossen'));
                        $start = $i;
                    }
                }
                return implode('<br>', $rows);
            case 'mms_cal':
                return esc_html(($d['calendar'] ?? 'AT') === 'BY' ? 'Bayern' : 'Österreich');
        }
        return '';
    }

    private static function usedIn(int $pageId): string
    {
        $where = [];
        foreach ([PostTypes::CATEGORY, PostTypes::DISPLAY] as $type) {
            foreach (PostTypes::choices($type) as $id => $title) {
                $d = PostTypes::data($id);
                if ($type === PostTypes::DISPLAY && empty($d['sequence_own'])) {
                    continue;
                }
                foreach ($d['sequence'] ?? [] as $e) {
                    if (($e['type'] ?? '') === 'page' && (int) ($e['page'] ?? 0) === $pageId) {
                        $where[] = esc_html(($type === PostTypes::CATEGORY ? 'Kategorie ' : '') . $title);
                        break;
                    }
                }
            }
        }
        return $where ? implode('<br>', $where) : '<span class="mms-warn-text">in keiner Abfolge – wird nicht angezeigt</span>';
    }
}
