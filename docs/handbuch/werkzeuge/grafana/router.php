<?php
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$boards = [
  '/d/zuschnitt' => ['Zuschnitt – Auftragsreihenfolge', [['Offene Aufträge', '17', '#73bf69'], ['Heute fertig', '42', '#73bf69'], ['Platten in Arbeit', '5', '#f2cc0c'], ['Ø Durchlaufzeit', '2,4 h', '#73bf69'], ['Kantenservice', '8', '#73bf69'], ['Störungen', '1', '#f2495c']]],
  '/d/hobelwerk' => ['Hobelwerk – Tagesleistung', [['Hobelware m³', '23,4', '#73bf69'], ['Ziel m³', '28,0', '#8ab8ff'], ['Auslastung', '84 %', '#f2cc0c'], ['Profile heute', '6', '#73bf69'], ['Rüstzeit', '38 min', '#73bf69'], ['Ausschuss', '1,9 %', '#73bf69']]],
];
if (!isset($boards[$p])) { http_response_code(404); echo 'not found'; return; }
[$title, $panels] = $boards[$p];
?><!DOCTYPE html><html><head><meta charset="utf-8"><title><?= $title ?></title><style>
body{margin:0;width:1920px;height:1080px;background:#111217;color:#ccccdc;font-family:Arial,sans-serif;display:grid;grid-template-columns:repeat(3,1fr);grid-template-rows:110px 1fr 1fr;gap:16px;padding:16px;box-sizing:border-box}
h1{grid-column:1/4;margin:0;font-size:46px;color:#fff;align-self:center;font-weight:500}
.p{background:#181b1f;border:1px solid #2c3235;border-radius:4px;padding:22px;font-size:30px}
.v{font-size:130px;font-weight:bold;margin-top:40px}
</style></head><body><h1><?= $title ?></h1>
<?php foreach ($panels as [$l, $v, $c]): ?><div class="p"><?= $l ?><div class="v" style="color:<?= $c ?>"><?= $v ?></div></div><?php endforeach; ?>
</body></html>
