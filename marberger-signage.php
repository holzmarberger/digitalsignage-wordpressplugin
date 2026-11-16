<?php
/**
 * Plugin Name:       Marberger Signage
 * Description:       Empfangs- und Info-Displays für Holz Marberger: Begrüßung, Wegweiser, Hinweise, Öffnungszeiten, Abfolgen und News.
 * Version:           1.0.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Holz Marberger GmbH
 * Text Domain:       marberger-signage
 * License:           Proprietary
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MMS_VERSION', '1.0.1');
define('MMS_FILE', __FILE__);
define('MMS_DIR', plugin_dir_path(__FILE__));
define('MMS_URL', plugin_dir_url(__FILE__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'Marberger\\Signage\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = MMS_DIR . 'includes/' . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($file)) {
        require $file;
    }
});

register_activation_hook(__FILE__, ['Marberger\\Signage\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['Marberger\\Signage\\Plugin', 'deactivate']);

add_action('plugins_loaded', ['Marberger\\Signage\\Plugin', 'boot']);
