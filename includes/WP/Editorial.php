<?php
namespace Marberger\Signage\WP;

use Marberger\Signage\Core\TimeBlocks;
use Marberger\Signage\Core\TimeRule;

/**
 * Bereinigung redaktioneller Eingaben – gemeinsam für Backend-Metaboxen und Handy-Pflegemaske.
 * Jede Methode liefert ['data' => array, 'errors' => list<string>, 'title' => ?string].
 */
final class Editorial
{
    public const NAME_MAX = 70;
    public const ICONS = [
        'construction' => 'Holzbau / Bauholz', 'decor' => 'Böden / Innenausbau', 'office' => 'Verwaltung / Büro',
        'parcel' => 'Paket', 'truck' => 'LKW / Anlieferung', 'info' => 'Info', 'person' => 'Person',
        'phone' => 'Telefon', 'stairs' => 'Treppe', 'door' => 'Tür / Eingang', 'wc' => 'WC', 'saw' => 'Säge / Zuschnitt',
    ];
    public const NOTICE_ICONS = ['right' => 'Pfeil rechts', 'left' => 'Pfeil links', 'up' => 'Pfeil geradeaus', 'info' => 'Info', 'warn' => 'Warnung'];

    public static function hasPlaceholder(string $s): bool
    {
        return (bool) preg_match('/\[[^\]]*\]/u', $s);
    }

    private static function s(array $in, string $k, int $max = 200): string
    {
        return mb_substr(trim(sanitize_text_field((string) ($in[$k] ?? ''))), 0, $max);
    }

    private static function dt(array $in, string $date, string $time): string
    {
        $d = (string) ($in[$date] ?? '');
        $t = TimeBlocks::time((string) ($in[$time] ?? '')) ?? '';
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && $t !== '' ? $d . ' ' . ($t === '24:00' ? '23:59' : $t) : '';
    }

    private static function ids($v): array
    {
        return array_values(array_unique(array_filter(array_map('absint', (array) $v))));
    }

    private static function days($v): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $v), static fn ($d) => $d >= 1 && $d <= 7)));
    }

    // --- Gruß ---------------------------------------------------------------

    public static function greeting(array $in): array
    {
        $errors = [];
        $name = self::s($in, 'name', self::NAME_MAX);
        $data = [
            'kicker_de' => self::s($in, 'kicker_de', 60) ?: 'Herzlich willkommen',
            'kicker_en' => self::s($in, 'kicker_en', 60),
            'sub_de' => self::s($in, 'sub_de', 140),
            'sub_en' => self::s($in, 'sub_en', 140),
            'logo' => absint($in['logo'] ?? 0),
            'from' => self::dt($in, 'from_date', 'from_time'),
            'to' => self::dt($in, 'to_date', 'to_time'),
            'daily' => !empty($in['daily']),
            'displays' => array_values(array_intersect(self::ids($in['displays'] ?? []), array_keys(PostTypes::displays('reception')))),
        ];
        if ($name === '') {
            $errors[] = 'Bitte Name bzw. Gruppe angeben.';
        }
        foreach (['name' => $name, 'kicker_de' => $data['kicker_de'], 'sub_de' => $data['sub_de'], 'kicker_en' => $data['kicker_en'], 'sub_en' => $data['sub_en']] as $v) {
            if (self::hasPlaceholder($v)) {
                $errors[] = 'Bitte die Platzhalter in eckigen Klammern (z. B. [Schule]) durch den echten Text ersetzen.';
                break;
            }
        }
        $errors = array_merge($errors, TimeRule::validate(self::greetingRule($data)));
        if (!$data['displays']) {
            $errors[] = 'Bitte mindestens ein Display wählen.';
        }
        return ['data' => $data, 'errors' => $errors, 'title' => $name];
    }

    public static function greetingRule(array $d): array
    {
        return ['type' => 'once', 'from' => $d['from'] ?? '', 'to' => $d['to'] ?? '', 'daily' => !empty($d['daily'])];
    }

    // --- Hinweis ------------------------------------------------------------

    public static function notice(array $in): array
    {
        $errors = [];
        $text = self::s($in, 'text_de', 160);
        $untilClose = !empty($in['until_close']);
        $data = [
            'text_de' => $text,
            'text_en' => self::s($in, 'text_en', 160),
            'icon' => array_key_exists($in['icon'] ?? '', self::NOTICE_ICONS) ? $in['icon'] : 'info',
            'days' => self::days($in['days'] ?? []),
            'from' => TimeBlocks::time((string) ($in['from'] ?? '')) ?? '',
            'to' => $untilClose ? 'close' : (TimeBlocks::time((string) ($in['to'] ?? '')) ?? ''),
            'valid_from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['valid_from'] ?? '')) ? $in['valid_from'] : '',
            'valid_to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['valid_to'] ?? '')) ? $in['valid_to'] : '',
            'displays' => self::ids($in['displays'] ?? []),
            'active' => !empty($in['active']),
            'priority' => max(0, min(99, (int) ($in['priority'] ?? 0))),
        ];
        if ($text === '') {
            $errors[] = 'Bitte einen Text angeben.';
        }
        $errors = array_merge($errors, TimeRule::validate(self::noticeRule($data)));
        if (!$data['displays']) {
            $errors[] = 'Bitte mindestens ein Display wählen.';
        }
        return ['data' => $data, 'errors' => $errors, 'title' => $text];
    }

    public static function noticeRule(array $d): array
    {
        return ['type' => 'weekly', 'days' => $d['days'] ?? [], 'from' => $d['from'] ?? '', 'to' => $d['to'] ?? '',
            'valid_from' => $d['valid_from'] ?? '', 'valid_to' => $d['valid_to'] ?? ''];
    }

    // --- Vollbild-Seite -----------------------------------------------------

    public static function fullpage(array $in): array
    {
        $type = ($in['rule_type'] ?? 'weekly') === 'once' ? 'once' : 'weekly';
        $data = [
            'rule_type' => $type,
            'days' => self::days($in['days'] ?? []),
            'from' => TimeBlocks::time((string) ($in['from'] ?? '')) ?? '',
            'to' => TimeBlocks::time((string) ($in['to'] ?? '')) ?? '',
            'once_from' => self::dt($in, 'once_from_date', 'once_from_time'),
            'once_to' => self::dt($in, 'once_to_date', 'once_to_time'),
            'open_days_only' => !empty($in['open_days_only']),
            'visitor_button' => !empty($in['visitor_button']),
            'displays' => self::ids($in['displays'] ?? []),
            'priority' => max(0, min(99, (int) ($in['priority'] ?? 0))),
        ];
        $errors = TimeRule::validate(self::fullpageRule($data));
        if (!$data['displays']) {
            $errors[] = 'Bitte mindestens ein Display wählen.';
        }
        return ['data' => $data, 'errors' => $errors, 'title' => null];
    }

    public static function fullpageRule(array $d): array
    {
        if (($d['rule_type'] ?? 'weekly') === 'once') {
            return ['type' => 'once', 'from' => $d['once_from'] ?? '', 'to' => $d['once_to'] ?? ''];
        }
        return ['type' => 'weekly', 'days' => $d['days'] ?? [], 'from' => $d['from'] ?? '', 'to' => $d['to'] ?? '', 'open_days_only' => !empty($d['open_days_only'])];
    }

    // --- News ---------------------------------------------------------------

    public static function news(array $in): array
    {
        $errors = [];
        $title = self::s($in, 'title', 140);
        $data = [
            'text' => mb_substr(sanitize_textarea_field((string) ($in['text'] ?? '')), 0, 600),
            'image' => absint($in['image'] ?? 0),
            'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['from'] ?? '')) ? $in['from'] : '',
            'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['to'] ?? '')) ? $in['to'] : '',
            'sites' => self::ids($in['sites'] ?? []),
            'slide' => !empty($in['slide']),
            'ticker' => !empty($in['ticker']),
        ];
        if ($title === '') {
            $errors[] = 'Bitte einen Titel angeben.';
        }
        if ($data['from'] === '' || $data['to'] === '') {
            $errors[] = 'Bitte „gültig von“ und „gültig bis“ angeben.';
        } elseif ($data['to'] < $data['from']) {
            $errors[] = '„Gültig bis“ liegt vor „gültig von“.';
        }
        if (!$data['sites']) {
            $errors[] = 'Bitte mindestens einen Standort wählen.';
        }
        if (!$data['slide'] && !$data['ticker']) {
            $errors[] = 'Bitte „als Folie“ und/oder „im Laufband“ wählen – sonst wird die News nirgends angezeigt.';
        }
        return ['data' => $data, 'errors' => $errors, 'title' => $title];
    }

    // --- Wegweiser-Kachel ---------------------------------------------------

    public static function node(array $in): array
    {
        $lines = static fn ($v) => array_values(array_filter(array_map('trim', explode("\n", sanitize_textarea_field((string) $v)))));
        $data = [
            'title_en' => self::s($in, 'title_en', 60),
            'sub_de' => self::s($in, 'sub_de', 90),
            'sub_en' => self::s($in, 'sub_en', 90),
            'icon' => array_key_exists($in['icon'] ?? '', self::ICONS) ? $in['icon'] : 'info',
            'media' => absint($in['media'] ?? 0),
            'steps_de' => array_slice($lines($in['steps_de'] ?? ''), 0, 8),
            'steps_en' => array_slice($lines($in['steps_en'] ?? ''), 0, 8),
            'contact' => self::s($in, 'contact', 90),
            'ext' => self::s($in, 'ext', 30),
            'photo' => absint($in['photo'] ?? 0),
            'qr_url' => esc_url_raw((string) ($in['qr_url'] ?? '')),
        ];
        return ['data' => $data, 'errors' => [], 'title' => null];
    }
}
