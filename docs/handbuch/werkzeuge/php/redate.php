<?php
/** Gruß-Termine auf Tage ohne Balken legen und den Gruß „Zimmerei Beispiel KG“ anlegen (Größenbeispiele). */
require __DIR__ . '/wp.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
use Marberger\Signage\WP\PostTypes;
use Marberger\Signage\WP\Editorial;
wp_set_current_user(1);
$ids = hb_read('ids.json');
$set = static function ($id, $from, $to): void {
    $d = PostTypes::data((int) $id);
    $d['from'] = $from;
    $d['to'] = $to;
    PostTypes::save((int) $id, $d);
    wp_update_post(['ID' => (int) $id]);
};
$set($ids['g_tischlerei'], '2026-12-03 08:00', '2026-12-03 17:00');
$set($ids['g_fachschule'], '2026-12-04 08:30', '2026-12-04 12:00');
$set($ids['g_abend'], '2026-12-09 18:00', '2026-12-09 22:00');
$tmp = wp_tempnam('z');
copy(HB_DATA . '/logo-zimmerei-beispiel.png', $tmp);
$logo = media_handle_sideload(['name' => 'logo-zimmerei-beispiel.png', 'tmp_name' => $tmp], 0);
$r = Editorial::greeting(['name' => 'Zimmerei Beispiel KG', 'kicker_de' => 'Herzlich willkommen', 'sub_de' => 'Wir freuen uns auf Ihren Besuch.',
    'kicker_en' => 'Welcome', 'sub_en' => 'We are delighted to welcome you.', 'logo' => $logo,
    'from_date' => '2026-12-21', 'from_time' => '08:00', 'to_date' => '2026-12-21', 'to_time' => '17:00', 'displays' => [$ids['rec_oetztal'], $ids['rec_silz']]]);
$id = wp_insert_post(['post_type' => PostTypes::GREETING, 'post_title' => 'Zimmerei Beispiel KG', 'post_status' => 'publish']);
PostTypes::save($id, $r['data'] + ['valid' => !$r['errors']]);
$ids['g_zimmerei'] = $id;
hb_write('ids.json', $ids);
echo "ok $id\n";
