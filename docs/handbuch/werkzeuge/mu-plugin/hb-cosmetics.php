<?php
// Nur Testumgebung: Lücken der älteren Sprachdatei schließen und Test-Hinweise ausblenden (für Handbuch-Screenshots).
add_filter('gettext', static function ($t, $text, $domain) {
    static $map = [
        'Collapse Menu' => 'Menü einklappen',
        'Published on: %s' => 'Veröffentlicht am: %s',
        'Post published.' => 'Beitrag veröffentlicht.',
        'Post updated.' => 'Beitrag aktualisiert.',
        'Post draft updated.' => 'Beitragsentwurf aktualisiert.',
        'Dismiss this notice.' => 'Diese Meldung ausblenden.',
        'Publish immediately' => 'Sofort veröffentlichen',
        'Schedule for: %s' => 'Planen für: %s',
        'Last edited by %1$s on %2$s at %3$s' => 'Zuletzt bearbeitet von %1$s am %2$s um %3$s',
        'M j, Y' => 'j. M Y',
        'M j, Y @ H:i' => 'j. M Y \u\m H:i',
        'Bulk actions' => 'Mehrfachaktionen',
        'All dates' => 'Alle Daten',
        'Filter' => 'Auswahl einschränken',
    ];
    return $map[$text] ?? $t;
}, 20, 3);
add_filter('gettext_with_context', static function ($t, $text, $ctx, $domain) {
    static $map = ['M j, Y' => 'j. M Y', 'Published on: %s' => 'Veröffentlicht am: %s', 'M j, Y @ H:i' => 'j. M Y \\u\\m H:i'];
    return $map[$text] ?? $t;
}, 20, 4);
add_filter('ngettext', static function ($t, $single, $plural, $n, $domain) {
    if ($single === '%s item') { return $n == 1 ? '%s Eintrag' : '%s Einträge'; }
    return $t;
}, 20, 5);
add_action('admin_head', static function () {
    echo '<style>#wp-admin-bar-sqlite-db-integration,#wpfooter,.update-nag,#wp-admin-bar-wp-logo{display:none!important}</style>';
});
add_action('wp_before_admin_bar_render', static function () {
    global $wp_admin_bar;
    foreach ($wp_admin_bar->get_nodes() as $n) { if (stripos((string) $n->title, 'SQLite') !== false) { $wp_admin_bar->remove_node($n->id); } }
});
