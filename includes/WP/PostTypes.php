<?php
namespace Marberger\Signage\WP;

/**
 * Inhaltstypen des Plugins. Alle Daten eines Eintrags liegen als Array im Meta-Feld '_mms'.
 * Redaktionelle Inhalte (Grüße, Hinweise, Wegweiser, Vollbild-Seiten, News, Display-Seiten) brauchen das Recht
 * 'mms_edit' (Display-Redaktion), Struktur (Displays, Kategorien, Standorte, Schließzeiten) 'manage_options'.
 */
final class PostTypes
{
    public const META = '_mms';

    public const GREETING = 'mms_greeting';
    public const NOTICE = 'mms_notice';
    public const NODE = 'mms_node';
    public const FULLPAGE = 'mms_fullpage';
    public const NEWS = 'mms_news';
    public const PAGE = 'mms_page';
    public const DISPLAY = 'mms_display';
    public const CATEGORY = 'mms_category';
    public const LOCATION = 'mms_location';
    public const CLOSURE = 'mms_closure';

    public const ADMIN_CAP = 'manage_options';
    public const EDIT_CAP = 'mms_edit';

    /** Reihenfolge = Reihenfolge im Menü „Displays“. */
    public static function definitions(): array
    {
        return [
            self::GREETING => ['Grüße', 'Gruß', self::EDIT_CAP, ['title']],
            self::NOTICE   => ['Hinweise', 'Hinweis', self::EDIT_CAP, ['title']],
            self::NODE     => ['Wegweiser-Kacheln', 'Wegweiser-Kachel', self::EDIT_CAP, ['title', 'page-attributes'], true],
            self::FULLPAGE => ['Vollbild-Seiten', 'Vollbild-Seite', self::EDIT_CAP, ['title', 'editor']],
            self::NEWS     => ['News', 'News', self::EDIT_CAP, ['title']],
            self::PAGE     => ['Display-Seiten', 'Display-Seite', self::EDIT_CAP, ['title', 'editor']],
            self::DISPLAY  => ['Displays', 'Display', self::ADMIN_CAP, ['title']],
            self::CATEGORY => ['Display-Kategorien', 'Display-Kategorie', self::ADMIN_CAP, ['title']],
            self::LOCATION => ['Standorte', 'Standort', self::ADMIN_CAP, ['title']],
            self::CLOSURE  => ['Schließzeiten', 'Schließzeit', self::ADMIN_CAP, ['title']],
        ];
    }

    public static function register(): void
    {
        foreach (self::definitions() as $type => $def) {
            [$plural, $singular, $cap, $supports] = $def;
            $hierarchical = !empty($def[4]);
            register_post_type($type, [
                'labels' => [
                    'name' => $plural,
                    'singular_name' => $singular,
                    'add_new' => 'Neu anlegen',
                    'add_new_item' => $singular . ' anlegen',
                    'edit_item' => $singular . ' bearbeiten',
                    'new_item' => 'Neu: ' . $singular,
                    'search_items' => $plural . ' durchsuchen',
                    'not_found' => 'Keine Einträge gefunden.',
                    'not_found_in_trash' => 'Keine Einträge im Papierkorb.',
                    'all_items' => $plural,
                    'menu_name' => $plural,
                    'parent_item_colon' => 'Übergeordnete Kachel:',
                    'attributes' => 'Einordnung',
                ],
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => 'mms',
                'show_in_rest' => false,
                'hierarchical' => $hierarchical,
                'supports' => $supports,
                'map_meta_cap' => false,
                'capabilities' => self::caps($cap),
                'rewrite' => false,
                'query_var' => false,
            ]);
        }
    }

    public static function caps(string $cap): array
    {
        $keys = ['edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts',
            'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts',
            'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts'];
        return array_fill_keys($keys, $cap);
    }

    public static function capFor(string $type): string
    {
        return self::definitions()[$type][2] ?? self::ADMIN_CAP;
    }

    public static function data(int $postId): array
    {
        $d = get_post_meta($postId, self::META, true);
        return is_array($d) ? $d : [];
    }

    public static function save(int $postId, array $data): void
    {
        update_post_meta($postId, self::META, $data);
    }

    /** @return array<int, string> id => Titel */
    public static function choices(string $type, array $args = []): array
    {
        $out = [];
        foreach (get_posts($args + ['post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']) as $p) {
            $out[$p->ID] = $p->post_title !== '' ? $p->post_title : ('#' . $p->ID);
        }
        return $out;
    }

    /** Displays eines Typs ('reception' | 'info' | '' = alle): id => Titel */
    public static function displays(string $type = ''): array
    {
        $out = [];
        foreach (self::choices(self::DISPLAY) as $id => $title) {
            if ($type !== '' && Store::displayType($id) !== $type) {
                continue;
            }
            $out[$id] = $title;
        }
        return $out;
    }
}
