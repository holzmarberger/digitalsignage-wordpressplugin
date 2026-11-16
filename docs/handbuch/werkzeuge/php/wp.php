<?php
/**
 * Gemeinsamer Einstieg der Hilfsskripte: lädt die Test-WordPress-Instanz und stellt die Arbeitsdateien bereit.
 * WP_DIR  – Verzeichnis der Testinstanz (enthält wp-load.php), Standard ~/hb-wp
 * HB_WORK – Arbeitsverzeichnis für ids.json, media.json usw., Standard docs/handbuch/arbeit
 */
$_SERVER['HTTP_HOST'] = 'localhost';
$hbWp = getenv('WP_DIR') ?: (getenv('HOME') . '/hb-wp');
if (!file_exists($hbWp . '/wp-load.php')) {
    fwrite(STDERR, "Keine WordPress-Testinstanz unter $hbWp (WP_DIR setzen).\n");
    exit(1);
}
require $hbWp . '/wp-load.php';
define('HB_WORK', getenv('HB_WORK') ?: dirname(__DIR__, 2) . '/arbeit');
define('HB_DATA', dirname(__DIR__) . '/beispieldaten');
define('HB_PLUGIN', dirname(__DIR__, 4));
if (!is_dir(HB_WORK)) {
    mkdir(HB_WORK, 0777, true);
}

function hb_read(string $name): array
{
    $f = HB_WORK . '/' . $name;
    return file_exists($f) ? (array) json_decode((string) file_get_contents($f), true) : [];
}

function hb_write(string $name, array $data): void
{
    file_put_contents(HB_WORK . '/' . $name, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
