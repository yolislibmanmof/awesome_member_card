<?php
global $dbs;
require_once __DIR__ . '/inc_card_render.php';

$S = amc_settings();
$mode = $_GET['mode'] ?? 'cards';
if (!in_array($mode, ['cards','cr80','a4front','a4back','fold'], true)) $mode = 'cards';
$res = amc_batch_members($dbs, [
    'type'  => (int)($_GET['type'] ?? 0),
    'year'  => preg_match('/^\d{4}$/', (string)($_GET['year'] ?? '')) ? $_GET['year'] : '',
    'q'     => trim((string)($_GET['q'] ?? '')),
    'limit' => (int)($_GET['limit'] ?? 0),
]);
$rows = [];
if ($res) { while ($m = $res->fetch_assoc()) $rows[] = $m; }
$count = count($rows);

$pagesize = ($mode === 'cr80')
    ? '@page{size:' . AMC_CW . 'mm ' . AMC_CH . 'mm;margin:0;}'
    : '@page{size:A4 portrait;margin:0;}';

$hints = [
    'cards'   => $count . ' kartu &mdash; HEMAT KERTAS: maksimal 3 anggota per lembar A4. Kedua sisi normal berdampingan; potong mengikuti tanda siku-L, lipat paruh KANAN ke belakang.',
    'cr80'    => $count . ' kartu &mdash; mode CR80: satu kartu per halaman untuk printer kartu bolak-balik otomatis.',
    'a4front' => $count . ' kartu &mdash; lembar A4 sisi DEPAN (8 kartu/lembar). Potong mengikuti tanda siku-L.',
    'a4back'  => $count . ' kartu &mdash; lembar A4 sisi BELAKANG (8 kartu/lembar). Cetak dengan urutan sama setelah membalik tumpukan.',
    'fold'    => $count . ' kartu &mdash; LIPAT PRESISI: potong pasangan, lipat paruh BAWAH ke belakang (sisi belakang tampak terputar 180 derajat di layar agar normal setelah dilipat).',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Produksi Kartu - <?= $count ?> kartu (<?= htmlspecialchars($mode) ?>)</title>
<?= amc_card_css() ?>
<style>
    <?= $pagesize ?>
    body{font-family:'Segoe UI',sans-serif;background:#eef2f7;margin:0;padding:26px 0;display:flex;flex-direction:column;align-items:center;gap:24px;}
    .pbtn{position:fixed;top:20px;right:24px;z-index:9;padding:11px 26px;background:linear-gradient(90deg,#1e3a8a,#3b82f6);color:#fff;border:none;border-radius:10px;cursor:pointer;font-size:14px;font-weight:700;}
    .hint{font-size:12px;color:#64748b;max-width:720px;text-align:center;line-height:1.6;}
    .stage{display:flex;flex-direction:column;gap:26px;align-items:center;}
    @media print{
        body{background:#fff;padding:0;gap:0;}
        .pbtn,.hint{display:none;}
        .stage{gap:0;display:block;}
        .stage > .sheet{page-break-after:always;box-shadow:none;}
        .stage > .sheet:last-child{page-break-after:auto;}
        .a4,.foldsheet,.sheet3{box-shadow:none;}
        .hpair,.foldpair{page-break-inside:avoid;}
    }
    @media screen{
        .a4,.foldsheet,.sheet3{box-shadow:0 10px 30px rgba(15,23,42,.25);}
    }
</style>
</head>
<body>
<button class="pbtn" onclick="window.print()">Cetak</button>
<div class="hint"><?= $hints[$mode] ?> Aktifkan <b>Background graphics</b> pada dialog cetak.</div>
<div class="stage">
<?php if ($mode === 'cards'): ?>
    <?php foreach (array_chunk($rows, 3) as $chunk): ?>
    <div class="sheet3">
        <?php foreach ($chunk as $m): $Sc = amc_card_settings_for($S, $m); ?>
        <div class="hpair">
            <div class="zone"><?= amc_sheet_front($m, $Sc) ?></div>
            <div class="zone"><?= amc_sheet_back($m, $Sc) ?></div>
            <div class="vfold"></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
<?php elseif ($mode === 'cr80'): ?>
    <?php foreach ($rows as $m): $Sc = amc_card_settings_for($S, $m); ?>
        <?= amc_sheet_front($m, $Sc) ?>
        <?= amc_sheet_back($m, $Sc) ?>
    <?php endforeach; ?>
<?php elseif ($mode === 'a4front' || $mode === 'a4back'): ?>
    <?php foreach (array_chunk($rows, 8) as $chunk): ?>
    <div class="a4">
        <?php foreach ($chunk as $m): $Sc = amc_card_settings_for($S, $m); ?>
        <div class="cell"><?= ($mode === 'a4front') ? amc_sheet_front($m, $Sc) : amc_sheet_back($m, $Sc) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <?php foreach (array_chunk($rows, 2) as $chunk): ?>
    <div class="foldsheet">
        <?php foreach ($chunk as $m): $Sc = amc_card_settings_for($S, $m); ?>
        <div class="foldpair">
            <div class="zone"><?= amc_sheet_front($m, $Sc) ?></div>
            <div class="zone rot180"><?= amc_sheet_back($m, $Sc) ?></div>
            <div class="foldline"></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>
</body>
</html>