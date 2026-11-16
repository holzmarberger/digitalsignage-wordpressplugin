<?php
namespace Marberger\Signage\WP\Admin;

use Marberger\Signage\Core\Settings;
use Marberger\Signage\Plugin;
use Marberger\Signage\WP\PostTypes;

final class Menu
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 9);
        add_action('admin_menu', [self::class, 'settingsMenu'], 20);
        add_action('admin_init', [self::class, 'registerSettings']);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_action('admin_notices', [Box::class, 'notices']);
        (new LocationBox())->register();
        (new ClosureBox())->register();
        (new CategoryBox())->register();
        (new DisplayBox())->register();
    }

    public static function menu(): void
    {
        add_menu_page('Displays', 'Displays', PostTypes::ADMIN_CAP, 'mms', [Overview::class, 'render'], 'dashicons-desktop', 58);
        add_submenu_page('mms', 'Übersicht', 'Übersicht', PostTypes::ADMIN_CAP, 'mms', [Overview::class, 'render']);
    }

    public static function settingsMenu(): void
    {
        add_submenu_page('mms', 'Einstellungen', 'Einstellungen', PostTypes::ADMIN_CAP, 'mms-settings', [self::class, 'settingsPage']);
    }

    public static function registerSettings(): void
    {
        register_setting('mms_settings', Plugin::OPTION, [
            'type' => 'array',
            'sanitize_callback' => static function ($in) {
                $in = is_array($in) ? $in : [];
                return ['min_gap' => min(120, absint($in['min_gap'] ?? 15))];
            },
        ]);
    }

    public static function settingsPage(): void
    {
        echo '<div class="wrap"><h1>Displays – Einstellungen</h1><form method="post" action="options.php">';
        settings_fields('mms_settings');
        echo '<table class="form-table">';
        echo Fields::row('Kurze Lücken ignorieren', Fields::number(Plugin::OPTION . '[min_gap]', Plugin::option('min_gap'), ['min' => 0, 'max' => 120]) . ' Minuten',
            'Lücken zwischen zwei Öffnungsblöcken, die kürzer sind, gelten nicht als Pause.');
        echo '</table>';
        submit_button();
        echo '</form></div>';
    }

    public static function assets(string $hook): void
    {
        $screen = get_current_screen();
        $ours = $screen && (in_array($screen->post_type, [PostTypes::LOCATION, PostTypes::CLOSURE, PostTypes::CATEGORY, PostTypes::DISPLAY], true) || strpos($hook, 'mms') !== false);
        if (!$ours) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style('mms-admin', MMS_URL . 'assets/admin/admin.css', [], MMS_VERSION);
        wp_enqueue_script('mms-admin', MMS_URL . 'assets/admin/admin.js', ['jquery'], MMS_VERSION, true);
        wp_localize_script('mms-admin', 'MMS', [
            'nonce' => wp_create_nonce('wp_rest'),
            'defaults' => ['reception' => Settings::defaults('reception'), 'info' => Settings::defaults('info')],
        ]);
    }
}
