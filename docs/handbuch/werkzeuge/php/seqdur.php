<?php
/** Abfolge-Dauern für die Info-Aufnahmen kurz setzen (Argument Sekunden) bzw. wiederherstellen (restore). */
require __DIR__ . '/wp.php';
use Marberger\Signage\WP\PostTypes;
$mode = $argv[1] ?? 'restore';
$bak = HB_WORK . '/seq-backup.json';
$ids = [];
foreach ([PostTypes::CATEGORY, PostTypes::DISPLAY] as $t) foreach (get_posts(['post_type' => $t, 'numberposts' => -1]) as $p) $ids[] = $p->ID;
if ($mode === 'restore') {
    if (!file_exists($bak)) exit;
    foreach (json_decode(file_get_contents($bak), true) as $id => $seq) { $d = PostTypes::data((int) $id); $d['sequence'] = $seq; PostTypes::save((int) $id, $d); }
    unlink($bak); echo "restored\n"; exit;
}
$backup = [];
foreach ($ids as $id) { $d = PostTypes::data($id); if (empty($d['sequence'])) continue; $backup[$id] = $d['sequence']; foreach ($d['sequence'] as &$e) $e['duration'] = (int) $mode; unset($e); PostTypes::save($id, $d); }
file_put_contents($bak, json_encode($backup)); echo "set\n";
