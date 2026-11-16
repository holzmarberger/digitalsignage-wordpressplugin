<?php
/** Testinstanz installieren: Admin admin/admin123, Zeitzone Wien, Sprache Deutsch, Plugin aktivieren. */
define('WP_INSTALLING', true);
require __DIR__ . '/wp.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if (!is_blog_installed()) {
    wp_install('Intranet Holz Marberger', 'admin', 'admin@example.com', true, '', 'admin123');
    echo "installiert\n";
}
update_option('timezone_string', 'Europe/Vienna');
update_option('WPLANG', 'de_DE');
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$res = activate_plugin('marberger-signage/marberger-signage.php');
echo is_wp_error($res) ? 'FEHLER: ' . $res->get_error_message() . "\n" : "Plugin aktiv\n";
