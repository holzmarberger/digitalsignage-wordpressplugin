<?php
/** Zusätzliche Grüße für die Größenbeispiele (Kap. 5) – direkt über die Plugin-Klassen. */
require __DIR__ . '/wp.php';
use Marberger\Signage\WP\PostTypes;
use Marberger\Signage\WP\Editorial;
wp_set_current_user(1);
$ids = hb_read('ids.json');
$m = hb_read('media.json');
$r = Editorial::greeting(['name' => 'Landesinnung Holzbau Tirol – Delegation aus Südtirol und Vorarlberg', 'kicker_de' => 'Herzlich willkommen',
    'sub_de' => 'Schön, dass Sie heute bei uns sind.', 'kicker_en' => 'Welcome', 'logo' => $m['logo-tischlerei-muster'],
    'from_date' => '2026-11-30', 'from_time' => '08:00', 'to_date' => '2026-11-30', 'to_time' => '17:00', 'displays' => [$ids['rec_oetztal'], $ids['rec_silz']]]);
$id = wp_insert_post(['post_type' => PostTypes::GREETING, 'post_title' => 'Landesinnung Holzbau Tirol – Delegation aus Südtirol und Vorarlberg', 'post_status' => 'publish']);
PostTypes::save($id, $r['data'] + ['valid' => !$r['errors']]);
$ids['g_long'] = $id;
hb_write('ids.json', $ids);
echo "ok $id\n";
