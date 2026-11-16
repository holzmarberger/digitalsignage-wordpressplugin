<?php
/** Nach den Masken-Aufnahmen: dort angelegten Beispielgruß und Papierkorb entfernen. */
require __DIR__ . '/wp.php';
foreach (get_posts(['post_type' => 'mms_greeting', 'post_status' => 'any', 'numberposts' => -1, 'title' => 'Lena und Jonas']) as $p) {
    wp_delete_post($p->ID, true);
}
foreach (get_posts(['post_type' => 'mms_greeting', 'post_status' => 'trash', 'numberposts' => -1]) as $p) {
    wp_delete_post($p->ID, true);
}
echo "ok\n";
