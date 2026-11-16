<?php
namespace Marberger\Signage\WP;

/**
 * Inhaltstypen des Plugins. Alle Daten eines Eintrags liegen als Array im Meta-Feld '_mms'.
 */
final class PostTypes
{
    public const META = '_mms';
    public const LOCATION = 'mms_location';
    public const CLOSURE = 'mms_closure';
    public const CATEGORY = 'mms_category';
    public const DISPLAY = 'mms_display';

    /** Paket 1: nur Administratoren. Paket 3 ergänzt eigene Rechte für die Display-Redaktion. */
    public const ADMIN_CAP = 'manage_options';

    public static function register(): void
    {
        $types = [
            self::DISPLAY  => ['Displays', 'Display'],
            self::CATEGORY => ['Display-Kategorien', 'Display-Kategorie'],
            self::LOCATION => ['Standorte', 'Standort'],
            self::CLOSURE  => ['Schließzeiten', 'Schließzeit'],
        ];
        foreach ($types as $type => [$plural, $singular]) {
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
                ],
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => 'mms',
                'show_in_rest' => false,
                'supports' => ['title'],
                'map_meta_cap' => false,
                'capabilities' => self::caps(self::ADMIN_CAP),
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
    public static function choices(string $type): array
    {
        $out = [];
        foreach (get_posts(['post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']) as $p) {
            $out[$p->ID] = $p->post_title !== '' ? $p->post_title : ('#' . $p->ID);
        }
        return $out;
    }
}
