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

    /** Inhalte für die Zustandsauflösung (Grüße, Hinweise, Vollbild-Seiten folgen in Paket 2). */
    public static function content(array $ctx): array
    {
        return apply_filters('mms_state_content', ['fullpages' => [], 'greetings' => [], 'notices' => []], $ctx);
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
