<?php
namespace Marberger\Signage;

use Marberger\Signage\WP\Admin\Menu;
use Marberger\Signage\WP\Player;
use Marberger\Signage\WP\PostTypes;
use Marberger\Signage\WP\Rest;

final class Plugin
{
    public const OPTION = 'mms_settings';

    public static function boot(): void
    {
        add_action('init', [PostTypes::class, 'register']);
        Rest::init();
        Player::init();
        if (is_admin()) {
            Menu::init();
        }
    }

    public static function activate(): void
    {
        PostTypes::register();
        if (get_option(self::OPTION) === false) {
            add_option(self::OPTION, self::defaultOptions());
        }
        self::createDefaultCategories();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public static function defaultOptions(): array
    {
        return ['min_gap' => 15];
    }

    public static function option(string $key)
    {
        $opts = wp_parse_args((array) get_option(self::OPTION, []), self::defaultOptions());
        return $opts[$key] ?? null;
    }

    private static function createDefaultCategories(): void
    {
        $existing = get_posts(['post_type' => PostTypes::CATEGORY, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids']);
        if ($existing) {
            return;
        }
        foreach (['Empfang' => 'reception', 'Info intern' => 'info'] as $title => $type) {
            $id = wp_insert_post(['post_type' => PostTypes::CATEGORY, 'post_title' => $title, 'post_status' => 'publish']);
            if ($id && !is_wp_error($id)) {
                update_post_meta($id, PostTypes::META, Core\Settings::defaults($type));
            }
        }
    }
}
