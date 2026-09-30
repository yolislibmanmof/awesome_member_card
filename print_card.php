<?php
global $dbs;
require_once __DIR__ . '/amc_lib.php';
if (is_file(__DIR__ . '/inc_card_render.php')) { require_once __DIR__ . '/inc_card_render.php'; }

$id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
if ($id <= 0) { die('ID anggota tidak valid.'); }
$stmt = $dbs->prepare("SELECT * FROM member WHERE member_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$m = $stmt->get_result()->fetch_assoc();
if (!$m) { die('Anggota tidak ditemukan.'); }

/* Ukuran kartu dengan cadangan aman: tidak pernah kosong */
$W  = (defined('AMC_CW') && is_numeric(AMC_CW)) ? (float)AMC_CW : 91;
$H  = (defined('AMC_CH') && is_numeric(AMC_CH)) ? (float)AMC_CH : 62;
$H2 = $H * 2;

/* Panduan potong & lipat vektor (mandiri, tidak bergantung berkas lain) */
if (!function_exists('amc_pc_guides')) {
    function amc_pc_guides($w, $h, $fold = null) {
        $m = 8; $gap = 3; $len = 5; $t = 0.2;
        $W = $w + 2 * $m; $H = $h + 2 * $m;
        $x0 = $m; $y0 = $m; $x1 = $m + $w; $y1 = $m + $h;
        $c = '#334155'; $L = [];
        $add = function ($xa, $ya, $xb, $yb, $dash = false) use (&$L, $c, $t) {
            $L[] = '<line x1="' . $xa . '" y1="' . $ya . '" x2="' . $xb . '" y2="' . $yb . '" stroke="' . $c . '" stroke-width="' . $t . '"' . ($dash ? ' stroke-dasharray="1.6 1.6"' : '') . '/>';
        };
        $add($x0 - $gap - $len, $y0, $x0 - $gap, $y0);
        $add($x1 + $gap, $y0, $x1 + $gap + $len, $y0);
        $add($x0 - $gap - $len, $y1, $x0 - $gap, $y1);
        $add($x1 + $gap, $y1, $x1 + $gap + $len, $y1);
        $add($x0, $y0 - $gap - $len, $x0, $y0 - $gap);
        $add($x1, $y0 - $gap - $len, $x1, $y0 - $gap);
        $add($x0, $y1 + $gap, $x0, $y1 + $gap + $len);
        $add($x1, $y1 + $gap, $x1, $y1 + $gap + $len);
        if ($fold === 'v') $add($m + $w / 2, $y0, $m + $w / 2, $y1, true);
        if ($fold === 'h') $add($x0, $m + $h / 2, $x1, $m + $h / 2, true);
        return '<svg class="guides" width="' . $W . 'mm" height="' . $H . 'mm" viewBox="0 0 ' . $W . ' ' . $H . '" overflow="visible" style="position:absolute;left:-' . $m . 'mm;top:-' . $m . 'mm;width:' . $W . 'mm;height:' . $H . 'mm;pointer-events:none;z-index:6;">' . implode('', $L) . '</svg>';
    }
}

$S      = amc_settings();
$code   = (string)($m['member_code'] ?? ($m['member_card_id'] ?? ($m['member_number'] ?? (string)$id)));
$name   = (string)($m['member_name'] ?? ($m['member_full_name'] ?? 'Anggota'));
$phone  = trim((string)($m['member_phone'] ?? ($m['phone'] ?? '')));
$since  = trim((string)($m['member_since'] ?? ($m['register_date'] ?? '')));
if ($since === '' || strpos($since, '0000') === 0) { $since = '-'; }
$valid  = date('d/m/Y', strtotime('+' . (int)$S['valid_years'] . ' years'));
$issued = date('d/m/Y');
$photo  = ($S['show_photo'] === '1') ? amc_member_photo($m) : null;
$logo   = amc_logo_data($S);
$sign   = amc_sign_data($S);
$style  = in_array($S['card_style'], ['aurora','glass','dark'], true) ? $S['card_style'] : 'aurora';
$font   = ['modern'=>"'Segoe UI',sans-serif",'serif'=>"Georgia,'Times New Roman',serif",'mono'=>"'Courier New',monospace"][$S['card_font'] ?? 'modern'] ?? "'Segoe UI',sans-serif";
$terms  = array_slice(preg_split('/\r?\n/', (string)($S['card_terms'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), 0, 6);
$signTitle = trim((string)($S['sign_title'] ?? 'Kepala Perpustakaan'));
$signName  = trim((string)($S['sign_name'] ?? ''));
$noise  = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120"><filter id="n"><feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2"/></filter><rect width="120" height="120" filter="url(%23n)" opacity="0.5"/></svg>';
$fx = '<div class="fx fx-blob"></div><div class="fx fx-rings"></div><div class="fx fx-beam"></div><div class="fx fx-noise"></div>'
    . ($style === 'dark' ? '<div class="fx fx-weave"></div>' : '')
    . ($S['show_holo'] === '1' ? '<div class="holo"></div>' : '') . '<div class="shine"></div>';
$imgLogo = 'style="width:100%;height:100%;object-fit:contain;display:block;"';
$imgPhoto = 'style="width:100%;height:100%;object-fit:cover;display:block;"';
$imgSignF = 'style="height:7mm;max-width:26mm;display:block;margin-bottom:.3mm;"';
$imgSignB = 'style="height:8mm;max-width:36mm;display:block;margin:0 auto;"';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cetak Kartu - <?= htmlspecialchars($name) ?></title>
<style>
    html,body{max-width:100%;}
    body{font-family:'Segoe UI',sans-serif;background:radial-gradient(1200px 600px at 50% -10%,#dbeafe,#eef2f7 60%);margin:0;padding:30px 0;display:flex;flex-direction:column;align-items:center;gap:22px;}
    .pbtn{position:fixed;top:22px;right:26px;z-index:9;padding:11px 28px;background:linear-gradient(90deg,<?= $S['primary_color'] ?>,<?= $S['secondary_color'] ?>);color:#fff;border:none;border-radius:10px;cursor:pointer;font-size:14px;font-weight:700;box-shadow:0 10px 24px rgba(30,58,138,.35);}
    .gbtn{position:fixed;top:22px;right:150px;z-index:9;padding:11px 20px;background:#334155;color:#fff;border:none;border-radius:10px;cursor:pointer;font-size:13px;font-weight:600;}
    .hint{font-size:12px;color:#64748b;max-width:680px;text-align:center;line-height:1.6;}
    .stage{display:flex;flex-direction:column;align-items:center;zoom:1.4;}
    .guides{pointer-events:none;z-index:6;}
    body.noguides .guides{display:none;}
    .foldpair{position:relative;width:<?= $W ?>mm;height:<?= $H2 ?>mm;max-width:100vw;display:flex;flex-direction:column;}
    .foldpair .sheet{border-radius:0;}
    .foldpair .sheet.brot{transform:rotate(180deg);}
    .sheet,.sheet *{box-sizing:border-box;}
    .sheet{position:relative;overflow:hidden;width:<?= $W ?>mm;height:<?= $H ?>mm;max-width:100vw;color:#fff;padding:4.5mm 5.5mm 4mm;display:flex;flex-direction:column;gap:1.4mm;box-shadow:0 26px 55px rgba(15,23,42,.4);-webkit-print-color-adjust:exact;print-color-adjust:exact;font-family:<?= $font ?>;}
    .sheet img{max-width:100%;}
    .st-aurora{background:linear-gradient(135deg,<?= $S['primary_color'] ?> 0%,<?= $S['secondary_color'] ?> 62%,<?= $S['accent_color'] ?> 135%);}
    .st-glass{background:linear-gradient(160deg,rgba(255,255,255,.34),rgba(255,255,255,.06)),linear-gradient(135deg,<?= $S['primary_color'] ?>,<?= $S['secondary_color'] ?>);border:1px solid rgba(255,255,255,.6);}
    .st-dark{background:linear-gradient(135deg,#0b1220,#16233f 55%,#0b1220);}
    .fx{position:absolute;inset:0;pointer-events:none;}
    .fx-blob{background:radial-gradient(circle at 86% 10%,<?= $S['accent_color'] ?> 0,transparent 44%),radial-gradient(circle at 8% 92%,#ffffff 0,transparent 36%),radial-gradient(1.2mm 1.2mm at 22% 26%,rgba(255,255,255,.9),transparent),radial-gradient(1mm 1mm at 68% 64%,rgba(255,255,255,.8),transparent);opacity:.5;filter:blur(3mm);}
    .fx-rings{background:repeating-radial-gradient(circle at 90% 88%,rgba(255,255,255,.16) 0 .35mm,transparent .35mm 2.6mm);opacity:.55;mix-blend-mode:overlay;}
    .fx-beam{background:linear-gradient(115deg,transparent 32%,rgba(255,255,255,.16) 46%,transparent 60%);}
    .fx-noise{background-image:url("<?= $noise ?>");opacity:.05;}
    .fx-weave{background:repeating-linear-gradient(115deg,transparent 0 3mm,<?= $S['accent_color'] ?>12 3mm 3.4mm),repeating-linear-gradient(25deg,transparent 0 5mm,rgba(255,255,255,.05) 5mm 5.4mm);}
    .holo{position:absolute;width:14mm;height:14mm;border-radius:50%;right:32mm;bottom:13mm;background:conic-gradient(#ff004c,#ff9a00,#00ffa3,#00c8ff,#a000ff,#ff004c);opacity:.28;mix-blend-mode:screen;filter:blur(.5mm);}
    .shine{position:absolute;inset:0;background:linear-gradient(105deg,transparent 42%,rgba(255,255,255,.3) 50%,transparent 58%);animation:sweep 4s linear infinite;}
    @keyframes sweep{0%{transform:translateX(-70%)}100%{transform:translateX(70%)}}
    .row{position:relative;z-index:3;display:flex;justify-content:space-between;align-items:center;gap:3mm;min-width:0;flex:none;}
    .brand{display:flex;gap:2.4mm;align-items:center;min-width:0;}
    .brand .tx{min-width:0;max-width:62mm;}
    .brand b{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;line-clamp:2;white-space:normal;overflow:hidden;font-size:3mm;line-height:1.12;letter-spacing:.3mm;text-transform:uppercase;}
    .brand span{display:block;font-size:1.8mm;opacity:.85;letter-spacing:.5mm;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .lg{width:7mm;height:7mm;flex:none;border-radius:1.8mm;background:#fff;color:<?= $S['primary_color'] ?>;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:3mm;overflow:hidden;box-shadow:0 1mm 3mm rgba(0,0,0,.25);}
    .chip{width:7mm;height:5mm;flex:none;border-radius:1.1mm;background:linear-gradient(135deg,<?= $S['accent_color'] ?>,#f59e0b);border:1px solid rgba(255,255,255,.4);}
    .mid{flex:1 1 auto;min-height:20mm;align-items:center;gap:4mm;}
    .photo{width:17mm;height:20mm;flex:none;border-radius:2.2mm;border:.5mm solid rgba(255,255,255,.8);background:rgba(255,255,255,.16);display:flex;align-items:center;justify-content:center;overflow:hidden;box-shadow:0 2mm 5mm rgba(0,0,0,.25);}
    .photo .ini{font-size:6.2mm;font-weight:800;text-shadow:0 1mm 2mm rgba(0,0,0,.3);}
    .who{flex:1;min-width:0;}
    .who h1{margin:0 0 .6mm;font-size:4.2mm;letter-spacing:.4mm;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-shadow:0 1mm 3mm rgba(0,0,0,.35);}
    .who .cd{font-family:'Courier New',monospace;font-size:2.8mm;letter-spacing:.9mm;opacity:.95;margin-bottom:1mm;}
    .flds{display:grid;grid-template-columns:1fr 1fr;gap:.5mm 4mm;font-size:2mm;line-height:1.2;}
    .flds div{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .flds label{opacity:.72;text-transform:uppercase;letter-spacing:.3mm;font-size:1.6mm;display:block;}
    .bot{align-items:flex-end;gap:3mm;flex:none;height:15mm;}
    .sign{font-size:1.9mm;opacity:.92;line-height:1.25;min-width:0;}
    .sign .line{width:26mm;border-bottom:.3mm solid rgba(255,255,255,.85);margin-bottom:.6mm;height:1.6mm;}
    .sign b{font-size:2mm;}
    .qr{background:#fff;border-radius:1.5mm;padding:.9mm .9mm .6mm;flex:none;display:flex;flex-direction:column;align-items:center;gap:.2mm;box-shadow:0 1mm 3mm rgba(0,0,0,.25);}
    .qr svg{width:12mm;height:12mm;}
    .qr .num{font-family:'Courier New',monospace;font-size:1.5mm;letter-spacing:.4mm;color:#0f172a;}
    .foot{position:relative;z-index:3;font-size:1.7mm;opacity:.72;letter-spacing:.3mm;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:none;}
    .stripe{position:relative;z-index:3;height:7mm;margin:-2mm -5.5mm 0;flex:none;background:linear-gradient(90deg,#0b1220,#26364f 45%,#0b1220);border-bottom:.6mm solid <?= $S['accent_color'] ?>;}
    .sigpanel{background:#fff;color:#0f172a;border-radius:1.6mm;padding:1.1mm 2.4mm .9mm;width:42mm;flex:none;text-align:center;}
    .sigpanel .space{height:6mm;}
    .sigpanel b{display:block;font-size:2mm;margin-top:.3mm;}
    .sigpanel span{font-size:1.6mm;line-height:1.2;text-transform:uppercase;letter-spacing:.3mm;color:#64748b;display:block;margin-top:.3mm;}
    .validbox{text-align:right;}
    .validbox .vl{display:inline-block;background:<?= $S['accent_color'] ?>;color:#1e293b;font-size:2mm;font-weight:800;padding:.7mm 2.2mm;border-radius:1.8mm;letter-spacing:.4mm;}
    .validbox b{display:block;font-size:4.2mm;letter-spacing:.5mm;margin-top:.7mm;text-shadow:0 1mm 2mm rgba(0,0,0,.35);}
    .validbox i{display:block;font-style:normal;font-size:1.8mm;opacity:.85;margin-top:.4mm;white-space:nowrap;}
    .terms{position:relative;z-index:3;flex:1 1 0;min-height:0;overflow:hidden;}
    .terms label{display:block;font-size:1.9mm;font-weight:800;letter-spacing:.5mm;text-transform:uppercase;color:<?= $S['accent_color'] ?>;margin-bottom:.8mm;}
    .terms ol{margin:0;padding-left:4mm;}
    .terms li{font-size:1.8mm;line-height:1.3;opacity:.94;margin-bottom:.3mm;}
    .backfoot{align-items:center;flex:none;}
    .backfoot .brand b{font-size:2.3mm;}
    .backfoot .lg{width:5.5mm;height:5.5mm;font-size:2.4mm;}
    @media print{
        @page{size:A4 portrait;margin:0;}
        body{background:#fff;padding:0;gap:0;min-height:100vh;justify-content:center;}
        .pbtn,.gbtn,.hint{display:none;}
        .stage{zoom:1;}
        .foldpair{page-break-inside:avoid;box-shadow:none;}
        .foldpair .sheet{box-shadow:none;}
        .shine{animation:none;opacity:.12;}
    }
</style>
</head>
<body>
<button class="pbtn" onclick="window.print()">Cetak</button>
<button class="gbtn" onclick="document.body.classList.toggle('noguides');this.textContent=document.body.classList.contains('noguides')?'Tampilkan Garis Bantu':'Sembunyikan Garis Bantu';">Sembunyikan Garis Bantu</button>
<div class="hint">Kartu <?= $W ?> x <?= $H ?> mm &mdash; pas untuk pouch laminating 68,5 x 97 mm. Potong mengikuti tanda sudut vektor, lalu lipat paruh bawah ke belakang pada garis putus-putus.</div>
<div class="stage">
    <div class="foldpair">
        <?= amc_pc_guides($W, $H2, 'h') ?>
        <div class="sheet st-<?= $style ?>">
            <?= $fx ?>
            <div class="row">
                <div class="brand">
                    <div class="lg"><?php if ($logo): ?><img src="<?= $logo ?>" alt="" <?= $imgLogo ?>><?php else: ?><?= htmlspecialchars(amc_initials($S['library_name'])) ?><?php endif; ?></div>
                    <div class="tx"><b><?= htmlspecialchars($S['library_name']) ?></b><span><?= htmlspecialchars($S['card_subtitle']) ?></span></div>
                </div>
                <?php if ($S['show_chip'] === '1'): ?><span class="chip"></span><?php endif; ?>
            </div>
            <div class="row mid">
                <?php if ($S['show_photo'] === '1'): ?>
                <div class="photo"><?php if ($photo): ?><img src="<?= htmlspecialchars($photo) ?>" alt="" <?= $imgPhoto ?>><?php else: ?><span class="ini"><?= htmlspecialchars(amc_initials($name)) ?></span><?php endif; ?></div>
                <?php endif; ?>
                <div class="who">
                    <h1><?= htmlspecialchars($name) ?></h1>
                    <div class="cd"><?= htmlspecialchars($code) ?></div>
                    <div class="flds">
                        <div><label>ID Anggota</label><?= $id ?></div>
                        <div><label>Terdaftar</label><?= htmlspecialchars($since) ?></div>
                        <?php if ($phone !== ''): ?><div style="grid-column:1/-1"><label>Telepon</label><?= htmlspecialchars($phone) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="row bot">
                <?php if ($S['show_signature'] === '1'): ?>
                <div class="sign">
                    <?php if ($sign): ?><img src="<?= $sign ?>" alt="" <?= $imgSignF ?>><?php endif; ?>
                    <div class="line"></div>
                    <?php if ($signName !== ''): ?><b><?= htmlspecialchars($signName) ?></b><br><?php endif; ?>
                    <?= htmlspecialchars($signTitle) ?> &mdash; <?= htmlspecialchars($S['library_name']) ?>
                </div>
                <?php else: ?><div></div><?php endif; ?>
                <?php if ($S['show_qr'] === '1'): ?>
                <div class="qr"><?= amc_qr_svg($code) ?><div class="num"><?= htmlspecialchars($code) ?></div></div>
                <?php endif; ?>
            </div>
            <div class="foot"><?= htmlspecialchars($S['footer_text']) ?></div>
        </div>
        <div class="sheet st-<?= $style ?> brot">
            <?= $fx ?>
            <div class="stripe"></div>
            <div class="row" style="margin-top:1.4mm;">
                <div class="sigpanel">
                    <?php if ($sign): ?><img src="<?= $sign ?>" alt="" <?= $imgSignB ?>><?php else: ?><div class="space"></div><?php endif; ?>
                    <?php if ($signName !== ''): ?><b><?= htmlspecialchars($signName) ?></b><?php endif; ?>
                    <span>Tanda Tangan <?= htmlspecialchars($signTitle) ?></span>
                </div>
                <div class="validbox">
                    <span class="vl">BERLAKU S/D</span>
                    <b><?= $valid ?></b>
                    <i>Diterbitkan: <?= $issued ?> &bull; ID <?= $id ?></i>
                </div>
            </div>
            <div class="terms">
                <label>Ketentuan Penggunaan Kartu</label>
                <ol>
                <?php foreach ($terms as $t): ?><li><?= htmlspecialchars($t) ?></li><?php endforeach; ?>
                </ol>
            </div>
            <div class="row backfoot">
                <div class="brand">
                    <div class="lg"><?php if ($logo): ?><img src="<?= $logo ?>" alt="" <?= $imgLogo ?>><?php else: ?><?= htmlspecialchars(amc_initials($S['library_name'])) ?><?php endif; ?></div>
                    <div class="tx"><b><?= htmlspecialchars($S['library_name']) ?></b></div>
                </div>
                <span style="font-family:'Courier New',monospace;font-size:2.1mm;letter-spacing:.9mm;opacity:.9;"><?= htmlspecialchars($code) ?></span>
            </div>
        </div>
    </div>
</div>
</body>
</html>