<?php
namespace Marberger\Signage\WP;

use Marberger\Signage\Core\TimeBlocks;
use Marberger\Signage\Core\TimeRule;

/**
 * Abfolge eines Info-Displays: Einträge vom Typ Web-Seite (URL), News-Folie oder Display-Seite.
 * Eintrag: {type, title, url, page, duration, days, from, to, width, height}
 */
final class Sequence
{
    public const TYPES = ['url' => 'Web-Seite (URL, z. B. Grafana)', 'news' => 'News-Folie', 'page' => 'Display-Seite'];

    public static function sanitize($rows): array
    {
        $out = [];
        foreach ((array) $rows as $r) {
            $type = array_key_exists($r['type'] ?? '', self::TYPES) ? $r['type'] : '';
            if ($type === '') {
                continue;
            }
            $e = [
                'type' => $type,
                'title' => mb_substr(sanitize_text_field((string) ($r['title'] ?? '')), 0, 80),
                'url' => $type === 'url' ? esc_url_raw((string) ($r['url'] ?? ''), ['http', 'https']) : '',
                'page' => $type === 'page' ? absint($r['page'] ?? 0) : 0,
                'duration' => absint($r['duration'] ?? 0),
                'days' => array_values(array_filter(array_map('intval', (array) ($r['days'] ?? [])), static fn ($d) => $d >= 1 && $d <= 7)),
                'from' => TimeBlocks::time((string) ($r['from'] ?? '')) ?? '',
                'to' => TimeBlocks::time((string) ($r['to'] ?? '')) ?? '',
                'width' => max(320, min(7680, absint($r['width'] ?? 1920) ?: 1920)),
                'height' => max(240, min(4320, absint($r['height'] ?? 1080) ?: 1080)),
            ];
            if (($type === 'url' && $e['url'] === '') || ($type === 'page' && !$e['page'])) {
                continue;
            }
            $out[] = $e;
        }
        return $out;
    }

    public static function rule(array $e): ?array
    {
        if (!$e['days'] && $e['from'] === '' && $e['to'] === '') {
            return null; // immer
        }
        return ['type' => 'weekly', 'days' => $e['days'] ?: [1, 2, 3, 4, 5, 6, 7], 'from' => $e['from'] ?: '00:00', 'to' => $e['to'] ?: '24:00'];
    }

    /**
     * Für den Player aufgelöste, gerade gültige Einträge.
     * @return list<array>
     */
    public static function resolve(array $entries, array $ctx, \DateTimeImmutable $now, array $news): array
    {
        $out = [];
        $default = (int) ($ctx['settings']['slide_duration'] ?? 30);
        foreach ($entries as $i => $e) {
            $rule = self::rule($e);
            if ($rule && !TimeRule::isActive($rule, $now, $ctx['calendar'])) {
                continue;
            }
            $item = ['id' => 'e' . $i, 'type' => $e['type'], 'title' => $e['title'], 'duration' => max(5, $e['duration'] ?: $default)];
            if ($e['type'] === 'url') {
                $item += ['url' => $e['url'], 'width' => $e['width'], 'height' => $e['height']];
                $item['title'] = $item['title'] ?: wp_parse_url($e['url'], PHP_URL_HOST);
            } elseif ($e['type'] === 'page') {
                $p = get_post($e['page']);
                if (!$p || $p->post_status !== 'publish') {
                    continue;
                }
                $item['title'] = $item['title'] ?: $p->post_title;
                $item['html'] = Store::renderContent($p->post_content);
            } else {
                $slides = array_values(array_filter($news, static fn ($n) => $n['slide']));
                if (!$slides) {
                    continue; // leere News-Folie überspringen
                }
                $item['title'] = $item['title'] ?: 'News';
                $item['news'] = $slides;
            }
            $out[] = $item;
        }
        return $out;
    }
}
