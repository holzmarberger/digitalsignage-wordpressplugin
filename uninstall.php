<?php
/**
 * Beim Löschen des Plugins: Einstellungen und geplante Aufgaben entfernen.
 * Inhalte (Displays, Grüße, Standorte …) bleiben erhalten, damit eine Neuinstallation nichts verliert.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
foreach (['mms_settings', 'mms_templates', 'mms_greet_pw', 'mms_greet_ver'] as $opt) {
    delete_option($opt);
}
wp_clear_scheduled_hook('mms_daily_cleanup');
