<?php
namespace Marberger\Signage\WP;

/** Tägliches Aufräumen: Grüße samt hochgeladenem Logo 30 Tage nach Ablauf löschen. */
final class Cleanup
{
    public const HOOK = 'mms_daily_cleanup';
    public const KEEP_DAYS = 30;

    public static function init(): void
    {
        add_action(self::HOOK, [self::class, 'run']);
        add_action('init', static function (): void {
            if (!wp_next_scheduled(self::HOOK)) {
                wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::HOOK);
            }
        });
    }

    public static function unschedule(): void
    {
        $ts = wp_next_scheduled(self::HOOK);
        if ($ts) {
            wp_unschedule_event($ts, self::HOOK);
        }
    }

    /** @return int Anzahl gelöschter Grüße */
    public static function run(): int
    {
        $limit = (new \DateTimeImmutable('now', Store::timezone()))->modify('-' . self::KEEP_DAYS . ' days')->format('Y-m-d H:i');
        $count = 0;
        foreach (get_posts(['post_type' => PostTypes::GREETING, 'post_status' => 'any', 'numberposts' => -1]) as $p) {
            $d = PostTypes::data($p->ID);
            if (empty($d['to']) || $d['to'] >= $limit) {
                continue;
            }
            $logo = (int) ($d['logo'] ?? 0);
            if ($logo && get_post_meta($logo, '_mms_upload', true)) {
                wp_delete_attachment($logo, true);
            }
            wp_delete_post($p->ID, true);
            $count++;
        }
        return $count;
    }
}
