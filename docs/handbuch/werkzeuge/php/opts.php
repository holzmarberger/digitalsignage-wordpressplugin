<?php
/** Allgemeine Optionen der Testinstanz und festes Wetter je Standort (kein Internet nötig). */
require __DIR__ . '/wp.php';
update_option('blogname', 'Intranet Holz Marberger');
update_option('blogdescription', '');
update_option('date_format', 'j. F Y');
update_option('time_format', 'H:i');
foreach ([[47.2386, 10.8593, 3, 'partly'], [47.2669, 10.9306, 4, 'sun']] as [$la, $lo, $t, $i]) {
    set_transient('mms_wx_' . md5(round($la, 3) . ',' . round($lo, 3)), ['data' => ['temp' => $t, 'icon' => $i]], 30 * DAY_IN_SECONDS);
}
echo "ok\n";
