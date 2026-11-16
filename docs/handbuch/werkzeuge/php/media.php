<?php
/** Beispielbilder in die Mediathek laden → media.json (Name → Attachment-ID). */
require __DIR__ . '/wp.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
wp_set_current_user(1);
// logo-zimmerei-beispiel wird erst in redate.php geladen (eigener Gruß)
$files = ['marberger-logo.png' => HB_PLUGIN . '/assets/img/marberger-logo.png'];
foreach (glob(HB_DATA . '/*') as $f) {
    if (basename($f) !== 'logo-zimmerei-beispiel.png') {
        $files[basename($f)] = $f;
    }
}
$out = [];
foreach ($files as $name => $src) {
    $tmp = wp_tempnam($name);
    copy($src, $tmp);
    $id = media_handle_sideload(['name' => $name, 'tmp_name' => $tmp], 0);
    $out[pathinfo($name, PATHINFO_FILENAME)] = is_wp_error($id) ? $id->get_error_message() : $id;
}
hb_write('media.json', $out);
$u = [];
foreach ($out as $k => $id) {
    $u[$k] = wp_get_attachment_url($id);
    $u[$k . '_large'] = wp_get_attachment_image_url($id, 'large');
}
hb_write('media-urls.json', $u);
echo json_encode($out), "\n";
