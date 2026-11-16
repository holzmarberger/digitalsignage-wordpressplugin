<?php
namespace Marberger\Signage\WP;

use Marberger\Signage\Core\OpeningCalendar;
use Marberger\Signage\Core\Settings;
use Marberger\Signage\Plugin;

/**
 * Übersetzt WordPress-Beiträge in die Arrays der Kernlogik.
 */
final class Store
{
    public const KEY_META = '_mms_key';
    public const SEEN_META = '_mms_last_seen';

    public static function timezone(): \DateTimeZone
    {
        return new \DateTimeZone('Europe/Vienna');
    }

    public static function displayByKey(string $key): ?\WP_Post
    {
        $posts = get_posts([
            'post_type' => PostTypes::DISPLAY, 'post_status' => 'publish', 'numberposts' => 1,
            'meta_key' => self::KEY_META, 'meta_value' => $key,
        ]);
        return $posts[0] ?? null;
    }

    /** ID eines Displays mit dieser Kennung in beliebigem Status (für Eindeutigkeitsprüfung), sonst null. */
    public static function displayByKeyAnyStatus(string $key): ?int
    {
        $ids = get_posts([
            'post_type' => PostTypes::DISPLAY, 'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'numberposts' => 1, 'fields' => 'ids', 'meta_key' => self::KEY_META, 'meta_value' => $key,
        ]);
        return $ids ? (int) $ids[0] : null;
    }

    /** Kalender-Konfiguration eines Standorts inkl. aller ihm zugeordneten Schließzeiten. */
    public static function calendarConfig(int $locationId): array
    {
        $loc = PostTypes::data($locationId);
        $closures = [];
        foreach (get_posts(['post_type' => PostTypes::CLOSURE, 'post_status' => 'publish', 'numberposts' => -1]) as $p) {
            $c = PostTypes::data($p->ID);
            if (empty($c['from']) || empty($c['to']) || !in_array($locationId, array_map('intval', $c['locations'] ?? []), true)) {
                continue;
            }
            $closures[] = [
                'id' => $p->ID, 'from' => $c['from'], 'to' => $c['to'],
                'label_de' => $c['label_de'] ?? $p->post_title, 'label_en' => $c['label_en'] ?? '',
                'text_de' => $c['text_de'] ?? '', 'text_en' => $c['text_en'] ?? '',
                'image' => !empty($c['image']) ? (string) wp_get_attachment_image_url((int) $c['image'], 'large') : '',
                'announce_days' => (int) ($c['announce_days'] ?? 14),
            ];
        }
        return [
            'weekly' => $loc['weekly'] ?? [],
            'calendar' => $loc['calendar'] ?? 'AT',
            'holiday_open' => $loc['holiday_open'] ?? [],
            'exceptions' => $loc['exceptions'] ?? [],
            'closures' => $closures,
            'min_gap' => (int) Plugin::option('min_gap'),
        ];
    }

    /** Ohne Standort: immer geöffnet, keine Feiertage. */
    public static function alwaysOpenConfig(): array
    {
        $all = [['00:00', '24:00']];
        return ['weekly' => array_fill(1, 7, $all), 'calendar' => '', 'holiday_open' => [], 'exceptions' => [], 'closures' => [], 'min_gap' => 0];
    }

    /**
     * Alles, was der Endpunkt für ein Display braucht. null bei unbekannter Kennung.
     */
    public static function displayContext(string $key): ?array
    {
        $post = self::displayByKey($key);
        if (!$post) {
            return null;
        }
        $d = PostTypes::data($post->ID);
        $categoryId = (int) ($d['category'] ?? 0);
        $category = $categoryId && get_post_status($categoryId) === 'publish' ? PostTypes::data($categoryId) : ['type' => 'reception'];
        $settings = Settings::merge($category, $d['overrides'] ?? []);

        $locationId = (int) ($d['location'] ?? 0);
        $location = null;
        if ($locationId && get_post_status($locationId) === 'publish') {
            $loc = PostTypes::data($locationId);
            $location = [
                'id' => $locationId,
                'name' => get_the_title($locationId),
                'logo' => !empty($loc['logo']) ? (string) wp_get_attachment_image_url((int) $loc['logo'], 'full') : '',
                'lat' => isset($loc['lat']) ? (float) $loc['lat'] : null,
                'lon' => isset($loc['lon']) ? (float) $loc['lon'] : null,
            ];
            $config = self::calendarConfig($locationId);
        } else {
            $config = self::alwaysOpenConfig();
            $settings['warn_enabled'] = false;
        }

        return [
            'post' => $post,
            'key' => $key,
            'name' => $post->post_title,
            'format' => ($d['format'] ?? 'hoch') === 'quer' ? 'quer' : 'hoch',
            'settings' => $settings,
            'location' => $location,
            'calendar' => new OpeningCalendar($config, self::timezone()),
        ];
    }

    /** 'reception' | 'info' eines Displays (aus seiner Kategorie). */
    public static function displayType(int $displayId): string
    {
        $cat = (int) (PostTypes::data($displayId)['category'] ?? 0);
        return $cat && (PostTypes::data($cat)['type'] ?? '') === 'info' ? 'info' : 'reception';
    }

    /** Gültige, veröffentlichte Einträge eines Typs, die diesem Display zugeordnet sind. */
    private static function forDisplay(string $type, int $displayId): array
    {
        $out = [];
        foreach (get_posts(['post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1]) as $p) {
            $d = PostTypes::data($p->ID);
            if (empty($d['valid']) || !in_array($displayId, array_map('intval', $d['displays'] ?? []), true)) {
                continue;
            }
            $out[] = [$p, $d];
        }
        return $out;
    }

    /** Inhalte für die Zustandsauflösung: Grüße, Hinweise, Vollbild-Seiten dieses Displays. */
    public static function content(array $ctx): array
    {
        $id = $ctx['post']->ID;
        $content = ['fullpages' => [], 'greetings' => [], 'notices' => []];
        foreach (self::forDisplay(PostTypes::GREETING, $id) as [$p, $d]) {
            $content['greetings'][] = [
                'id' => $p->ID, 'rule' => Editorial::greetingRule($d), 'modified' => strtotime($p->post_modified_gmt),
                'name' => $p->post_title, 'kicker_de' => $d['kicker_de'] ?? '', 'kicker_en' => $d['kicker_en'] ?? '',
                'sub_de' => $d['sub_de'] ?? '', 'sub_en' => $d['sub_en'] ?? '',
                'logo' => !empty($d['logo']) ? (string) wp_get_attachment_image_url((int) $d['logo'], 'large') : '',
            ];
        }
        foreach (self::forDisplay(PostTypes::NOTICE, $id) as [$p, $d]) {
            if (empty($d['active'])) {
                continue;
            }
            $content['notices'][] = [
                'id' => $p->ID, 'rule' => Editorial::noticeRule($d), 'priority' => (int) ($d['priority'] ?? 0),
                'modified' => strtotime($p->post_modified_gmt), 'text_de' => $p->post_title, 'text_en' => $d['text_en'] ?? '', 'icon' => $d['icon'] ?? 'info',
            ];
        }
        foreach (self::forDisplay(PostTypes::FULLPAGE, $id) as [$p, $d]) {
            $content['fullpages'][] = [
                'id' => $p->ID, 'rule' => Editorial::fullpageRule($d), 'priority' => (int) ($d['priority'] ?? 0),
                'modified' => strtotime($p->post_modified_gmt), 'title' => $p->post_title,
                'html' => self::renderContent($p->post_content), 'visitor_button' => !empty($d['visitor_button']),
            ];
        }
        return apply_filters('mms_state_content', $content, $ctx);
    }

    /** Editor-Inhalt für die Anzeige (ohne Theme-Filter). */
    public static function renderContent(string $content): string
    {
        $html = function_exists('do_blocks') ? do_blocks($content) : $content;
        return wp_kses_post(wpautop(wptexturize($html)));
    }

    /** @return array{url:string,type:string}|null */
    public static function media(int $attachmentId): ?array
    {
        if (!$attachmentId) {
            return null;
        }
        $url = wp_get_attachment_url($attachmentId);
        if (!$url) {
            return null;
        }
        $mime = (string) get_post_mime_type($attachmentId);
        return ['url' => $url, 'type' => strpos($mime, 'video/') === 0 ? 'video' : 'image'];
    }

    /** Wegweiser-Baum eines Displays (Kinder des Startknotens bzw. oberste Ebene), max. 8 je Ebene. */
    public static function directory(array $ctx): array
    {
        $all = get_posts(['post_type' => PostTypes::NODE, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC']]);
        $byParent = [];
        foreach ($all as $p) {
            $byParent[(int) $p->post_parent][] = $p;
        }
        $start = (int) (PostTypes::data($ctx['post']->ID)['start_node'] ?? 0);
        $build = static function (int $parent, int $depth) use (&$build, $byParent): array {
            $out = [];
            foreach (array_slice($byParent[$parent] ?? [], 0, 8) as $p) {
                $d = PostTypes::data($p->ID);
                $children = $depth < 4 ? $build($p->ID, $depth + 1) : [];
                $node = [
                    'id' => $p->ID, 'title_de' => $p->post_title, 'title_en' => ($d['title_en'] ?? '') ?: $p->post_title,
                    'sub_de' => $d['sub_de'] ?? '', 'sub_en' => ($d['sub_en'] ?? '') ?: ($d['sub_de'] ?? ''), 'icon' => $d['icon'] ?? 'info',
                    'children' => $children,
                ];
                if (!$children) {
                    $node += [
                        'media' => self::media((int) ($d['media'] ?? 0)),
                        'steps_de' => $d['steps_de'] ?? [], 'steps_en' => ($d['steps_en'] ?? []) ?: ($d['steps_de'] ?? []),
                        'contact' => $d['contact'] ?? '', 'ext' => $d['ext'] ?? '',
                        'photo' => !empty($d['photo']) ? (string) wp_get_attachment_image_url((int) $d['photo'], 'medium') : '',
                        'qr_url' => $d['qr_url'] ?? '',
                    ];
                }
                $out[] = $node;
            }
            return $out;
        };
        return $build($start, 0);
    }

    /** Gültige News eines Standorts am Tag von $now. */
    public static function news(?int $locationId, \DateTimeImmutable $now): array
    {
        if (!$locationId) {
            return [];
        }
        $today = $now->format('Y-m-d');
        $out = [];
        foreach (get_posts(['post_type' => PostTypes::NEWS, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC']) as $p) {
            $d = PostTypes::data($p->ID);
            if (empty($d['valid']) || ($d['from'] ?? '') > $today || ($d['to'] ?? '') < $today
                || !in_array($locationId, array_map('intval', $d['sites'] ?? []), true)) {
                continue;
            }
            $out[] = [
                'id' => $p->ID, 'title' => $p->post_title, 'text' => $d['text'] ?? '',
                'image' => !empty($d['image']) ? (string) wp_get_attachment_image_url((int) $d['image'], 'large') : '',
                'to' => $d['to'], 'slide' => !empty($d['slide']), 'ticker' => !empty($d['ticker']),
            ];
        }
        return $out;
    }

    /** Abfolge eines Info-Displays: eigene oder die der Kategorie. */
    public static function sequenceEntries(array $ctx): array
    {
        $d = PostTypes::data($ctx['post']->ID);
        if (!empty($d['sequence_own'])) {
            return $d['sequence'] ?? [];
        }
        $cat = (int) ($d['category'] ?? 0);
        return $cat ? (PostTypes::data($cat)['sequence'] ?? []) : [];
    }

    public static function touch(int $displayId): void
    {
        $last = (int) get_post_meta($displayId, self::SEEN_META, true);
        if (time() - $last >= 30) {
            update_post_meta($displayId, self::SEEN_META, time());
        }
    }

    public static function lastSeen(int $displayId): int
    {
        return (int) get_post_meta($displayId, self::SEEN_META, true);
    }
}
