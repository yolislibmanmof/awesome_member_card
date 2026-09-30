<?php
defined('INDEX_AUTH') OR die('Direct access not allowed!');
require_once __DIR__ . '/amc_lib.php';

/* ==== KALIBRASI UKURAN KARTU (mm) ==== */
if (!defined('AMC_CW')) define('AMC_CW', 91);
if (!defined('AMC_CH')) define('AMC_CH', 62);

/* ==== PARAMETER TANDA POTONG PRESISI ====
   O = gap dari tepi kartu, L = panjang lengan, T = ketebalan */
if (!defined('AMC_MO')) define('AMC_MO', 3);
if (!defined('AMC_ML')) define('AMC_ML', 5);
if (!defined('AMC_MT')) define('AMC_MT', 0.2);

if (!function_exists('amc_card_settings_for')) {
    function amc_card_settings_for($S, $m) {
        static $tpl = null;
        if ($tpl === null) { $tpl = json_decode((string)($S['type_templates'] ?? ''), true); if (!is_array($tpl)) $tpl = []; }
        $tid = (string)($m['member_type_id'] ?? '');
        if ($tid !== '' && isset($tpl[$tid]) && is_array($tpl[$tid]) && $tpl[$tid]) {
            return array_merge($S, $tpl[$tid]);
        }
        return $S;
    }
}

if (!function_exists('amc_card_css')) {
    function amc_card_css() {
        $W = AMC_CW; $H = AMC_CH; $W2 = $W * 2; $H2 = $H * 2;
        $X = AMC_MO + AMC_ML;           // jarak luar total pseudo (8mm)
        $T = AMC_MT; $L = AMC_ML; $Cc = '#334155';
        return '<style>
.sheet,.sheet *{box-sizing:border-box;}
.sheet{--p:#1e3a8a;--s:#3b82f6;--a:#fbbf24;position:relative;overflow:hidden;width:' . $W . 'mm;height:' . $H . 'mm;border-radius:3.5mm;color:#fff;padding:4.5mm 5.5mm 4mm;display:flex;flex-direction:column;gap:1.4mm;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
.sheet.f-modern{font-family:"Segoe UI",sans-serif;}.sheet.f-serif{font-family:Georgia,"Times New Roman",serif;}.sheet.f-mono{font-family:"Courier New",monospace;}
.st-aurora{background:linear-gradient(135deg,var(--p) 0%,var(--s) 62%,var(--a) 135%);}
.st-glass{background:linear-gradient(160deg,rgba(255,255,255,.34),rgba(255,255,255,.06)),linear-gradient(135deg,var(--p),var(--s));border:1px solid rgba(255,255,255,.6);}
.st-dark{background:linear-gradient(135deg,#0b1220,#16233f 55%,#0b1220);}
.fx{position:absolute;inset:0;pointer-events:none;}
.fx-blob{background:radial-gradient(circle at 86% 10%,var(--a) 0,transparent 44%),radial-gradient(circle at 8% 92%,#ffffff 0,transparent 36%);opacity:.5;filter:blur(3mm);}
.fx-rings{background:repeating-radial-gradient(circle at 90% 88%,rgba(255,255,255,.16) 0 .35mm,transparent .35mm 2.6mm);opacity:.55;mix-blend-mode:overlay;}
.fx-beam{background:linear-gradient(115deg,transparent 32%,rgba(255,255,255,.16) 46%,transparent 60%);}
.st-dark .fx-weave{background:repeating-linear-gradient(115deg,transparent 0 3mm,rgba(255,255,255,.07) 3mm 3.4mm);}
.holo{position:absolute;width:14mm;height:14mm;border-radius:50%;right:32mm;bottom:13mm;background:conic-gradient(#ff004c,#ff9a00,#00ffa3,#00c8ff,#a000ff,#ff004c);opacity:.28;mix-blend-mode:screen;filter:blur(.5mm);}
.row{position:relative;z-index:3;display:flex;justify-content:space-between;align-items:center;gap:3mm;min-width:0;flex:none;}
.brand{display:flex;gap:2.4mm;align-items:center;min-width:0;}
.brand .tx{min-width:0;max-width:62mm;}
.brand b{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;white-space:normal;overflow:hidden;font-size:3mm;line-height:1.12;letter-spacing:.3mm;text-transform:uppercase;}
.brand span{display:block;font-size:1.8mm;opacity:.85;letter-spacing:.5mm;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.lg{width:7mm;height:7mm;flex:none;border-radius:1.8mm;background:#fff;color:var(--p);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:3mm;overflow:hidden;}
.lg img{width:100%;height:100%;object-fit:contain;}
.chip{width:7mm;height:5mm;flex:none;border-radius:1.1mm;background:linear-gradient(135deg,var(--a),#f59e0b);border:1px solid rgba(255,255,255,.4);}
.mid{flex:1 1 auto;min-height:20mm;align-items:center;gap:4mm;}
.photo{width:17mm;height:20mm;flex:none;border-radius:2.2mm;border:.5mm solid rgba(255,255,255,.8);background:rgba(255,255,255,.16);display:flex;align-items:center;justify-content:center;overflow:hidden;}
.photo img{width:100%;height:100%;object-fit:cover;}
.photo .ini{font-size:6.2mm;font-weight:800;}
.who{flex:1;min-width:0;}
.who h1{margin:0 0 .6mm;font-size:4.2mm;letter-spacing:.4mm;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.who .cd{font-family:"Courier New",monospace;font-size:2.8mm;letter-spacing:.9mm;opacity:.95;margin-bottom:1mm;}
.flds{display:grid;grid-template-columns:1fr 1fr;gap:.5mm 4mm;font-size:2mm;line-height:1.2;}
.flds div{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.flds label{opacity:.72;text-transform:uppercase;letter-spacing:.3mm;font-size:1.6mm;display:block;}
.bot{align-items:flex-end;gap:3mm;flex:none;height:15mm;}
.sign{font-size:1.9mm;opacity:.92;line-height:1.25;min-width:0;}
.sign img{height:7mm;display:block;margin-bottom:.3mm;}
.sign .line{width:26mm;border-bottom:.3mm solid rgba(255,255,255,.85);margin-bottom:.6mm;height:1.6mm;}
.sign b{font-size:2mm;}
.qr{background:#fff;border-radius:1.5mm;padding:.9mm .9mm .6mm;flex:none;display:flex;flex-direction:column;align-items:center;gap:.2mm;}
.qr svg{width:12mm;height:12mm;}
.qr .num{font-family:"Courier New",monospace;font-size:1.5mm;letter-spacing:.4mm;color:#0f172a;}
.foot{position:relative;z-index:3;font-size:1.7mm;opacity:.72;letter-spacing:.3mm;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:none;}
.stripe{position:relative;z-index:3;height:7mm;margin:-2mm -5.5mm 0;flex:none;background:linear-gradient(90deg,#0b1220,#26364f 45%,#0b1220);border-bottom:.6mm solid var(--a);}
.sigpanel{background:#fff;color:#0f172a;border-radius:1.6mm;padding:1.1mm 2.4mm .9mm;width:42mm;flex:none;text-align:center;}
.sigpanel .space{height:6mm;}
.sigpanel img{height:8mm;display:block;margin:0 auto;}
.sigpanel b{display:block;font-size:2mm;margin-top:.3mm;}
.sigpanel span{font-size:1.6mm;line-height:1.2;text-transform:uppercase;letter-spacing:.3mm;color:#64748b;display:block;margin-top:.3mm;}
.validbox{text-align:right;}
.validbox .vl{display:inline-block;background:var(--a);color:#1e293b;font-size:2mm;font-weight:800;padding:.7mm 2.2mm;border-radius:1.8mm;letter-spacing:.4mm;}
.validbox b{display:block;font-size:4.2mm;letter-spacing:.5mm;margin-top:.7mm;}
.validbox i{display:block;font-style:normal;font-size:1.8mm;opacity:.85;margin-top:.4mm;white-space:nowrap;}
.terms{position:relative;z-index:3;flex:1 1 0;min-height:0;overflow:hidden;}
.terms label{display:block;font-size:1.9mm;font-weight:800;letter-spacing:.5mm;text-transform:uppercase;color:var(--a);margin-bottom:.8mm;}
.terms ol{margin:0;padding-left:4mm;}
.terms li{font-size:1.8mm;line-height:1.3;opacity:.94;margin-bottom:.3mm;}
.backfoot{align-items:center;flex:none;}
.backfoot .brand b{font-size:2.3mm;}
.backfoot .lg{width:5.5mm;height:5.5mm;font-size:2.4mm;}
/* ==== TANDA POTONG SIKU-L PRESISI (gap 3mm, lengan 5mm, tebal 0.2mm) ==== */
.cell::before,.hpair::before,.foldpair::before{content:"";position:absolute;left:-' . $X . 'mm;right:-' . $X . 'mm;top:-' . $X . 'mm;height:' . $X . 'mm;pointer-events:none;background:
 linear-gradient(' . $Cc . ',' . $Cc . ') left ' . $X . 'mm top 0/' . $T . 'mm ' . $L . 'mm no-repeat,
 linear-gradient(' . $Cc . ',' . $Cc . ') right ' . $X . 'mm top 0/' . $T . 'mm ' . $L . 'mm no-repeat,
 linear-gradient(' . $Cc . ',' . $Cc . ') left 0 bottom 0/' . $L . 'mm ' . $T . 'mm no-repeat,
 linear-gradient(' . $Cc . ',' . $Cc . ') right 0 bottom 0/' . $L . 'mm ' . $T . 'mm no-repeat;}
.cell::after,.hpair::after,.foldpair::after{content:"";position:absolute;left:-' . $X . 'mm;right:-' . $X . 'mm;bottom:-' . $X . 'mm;height:' . $X . 'mm;pointer-events:none;background:
 linear-gradient(' . $Cc . ',' . $Cc . ') left ' . $X . 'mm bottom 0/' . $T . 'mm ' . $L . 'mm no-repeat,
 linear-gradient(' . $Cc . ',' . $Cc . ') right ' . $X . 'mm bottom 0/' . $T . 'mm ' . $L . 'mm no-repeat,
 linear-gradient(' . $Cc . ',' . $Cc . ') left 0 top 0/' . $L . 'mm ' . $T . 'mm no-repeat,
 linear-gradient(' . $Cc . ',' . $Cc . ') right 0 top 0/' . $L . 'mm ' . $T . 'mm no-repeat;}
/* ==== GARIS LIPAT POLA TERATUR ==== */
.vfold{position:absolute;top:-' . AMC_MO . 'mm;bottom:-' . AMC_MO . 'mm;left:' . $W . 'mm;width:' . $T . 'mm;margin-left:-' . ($T / 2) . 'mm;background:repeating-linear-gradient(180deg,' . $Cc . ' 0 2mm,transparent 2mm 4mm);}
.foldline{position:absolute;left:-' . $X . 'mm;right:-' . $X . 'mm;top:' . $H . 'mm;height:' . $T . 'mm;margin-top:-' . ($T / 2) . 'mm;background:repeating-linear-gradient(90deg,' . $Cc . ' 0 2mm,transparent 2mm 4mm);}
/* ==== LEMBAR PRODUKSI DETERMINISTIK ==== */
.sheet3,.foldsheet{width:210mm;height:297mm;background:#fff;page-break-after:always;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;gap:12mm;padding:14mm 0;}
.sheet3:last-child,.foldsheet:last-child{page-break-after:auto;}
.a4{width:210mm;height:297mm;padding:10mm;display:grid;grid-template-columns:repeat(2,' . $W . 'mm);grid-auto-rows:' . $H . 'mm;gap:8mm;page-break-after:always;background:#fff;}
.a4:last-child{page-break-after:auto;}
.cell{position:relative;width:' . $W . 'mm;height:' . $H . 'mm;}
.cell .sheet{border-radius:2mm;}
.hpair{position:relative;width:' . $W2 . 'mm;height:' . $H . 'mm;display:flex;}
.hpair .zone{width:' . $W . 'mm;height:' . $H . 'mm;position:relative;}
.hpair .zone .sheet{position:absolute;inset:0;border-radius:0;}
.foldpair{position:relative;width:' . $W . 'mm;height:' . $H2 . 'mm;}
.foldpair .zone{width:' . $W . 'mm;height:' . $H . 'mm;position:relative;}
.foldpair .zone .sheet{position:absolute;inset:0;border-radius:0;}
.foldpair .zone.rot180 .sheet{transform:rotate(180deg);}
@media print{
 .pbtn,.hint{display:none !important;}
}
</style>';
    }
}

if (!function_exists('amc_sheet_vars')) {
    function amc_sheet_vars($S) {
        return 'style="--p:' . htmlspecialchars($S['primary_color']) . ';--s:' . htmlspecialchars($S['secondary_color']) . ';--a:' . htmlspecialchars($S['accent_color']) . '"';
    }
}

if (!function_exists('amc_sheet_front')) {
    function amc_sheet_front($m, $S) {
        $id    = (int)($m['member_id'] ?? 0);
        $code  = (string)($m['member_code'] ?? ($m['member_card_id'] ?? ($m['member_number'] ?? (string)$id)));
        $name  = (string)($m['member_name'] ?? ($m['member_full_name'] ?? 'Anggota'));
        $phone = trim((string)($m['member_phone'] ?? ($m['phone'] ?? '')));
        $since = trim((string)($m['member_since'] ?? ($m['register_date'] ?? '')));
        if ($since === '' || strpos($since, '0000') === 0) { $since = '-'; }
        $photo = ($S['show_photo'] === '1') ? amc_member_photo($m) : null;
        $logo  = amc_logo_data($S);
        $sign  = amc_sign_data($S);
        $style = in_array($S['card_style'] ?? 'aurora', ['aurora','glass','dark'], true) ? $S['card_style'] : 'aurora';
        $fontC = 'f-' . ($S['card_font'] ?? 'modern');
        $signTitle = trim((string)($S['sign_title'] ?? 'Kepala Perpustakaan'));
        $signName  = trim((string)($S['sign_name'] ?? ''));
        ob_start(); ?>
<div class="sheet st-<?= $style ?> <?= $fontC ?>" <?= amc_sheet_vars($S) ?>>
    <div class="fx fx-blob"></div><div class="fx fx-rings"></div><div class="fx fx-beam"></div><?php if ($style==='dark'): ?><div class="fx fx-weave"></div><?php endif; if ($S['show_holo']==='1'): ?><div class="holo"></div><?php endif; ?>
    <div class="row">
        <div class="brand">
            <div class="lg"><?php if ($logo): ?><img src="<?= $logo ?>" alt=""><?php else: ?><?= htmlspecialchars(amc_initials($S['library_name'])) ?><?php endif; ?></div>
            <div class="tx"><b><?= htmlspecialchars($S['library_name']) ?></b><span><?= htmlspecialchars($S['card_subtitle']) ?></span></div>
        </div>
        <?php if ($S['show_chip']==='1'): ?><span class="chip"></span><?php endif; ?>
    </div>
    <div class="row mid">
        <?php if ($S['show_photo']==='1'): ?>
        <div class="photo"><?php if ($photo): ?><img src="<?= htmlspecialchars($photo) ?>" alt=""><?php else: ?><span class="ini"><?= htmlspecialchars(amc_initials($name)) ?></span><?php endif; ?></div>
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
        <?php if ($S['show_signature']==='1'): ?>
        <div class="sign">
            <?php if ($sign): ?><img src="<?= $sign ?>" alt=""><?php endif; ?>
            <div class="line"></div>
            <?php if ($signName !== ''): ?><b><?= htmlspecialchars($signName) ?></b><br><?php endif; ?>
            <?= htmlspecialchars($signTitle) ?> &mdash; <?= htmlspecialchars($S['library_name']) ?>
        </div>
        <?php else: ?><div></div><?php endif; ?>
        <?php if ($S['show_qr']==='1'): ?>
        <div class="qr"><?= amc_qr_svg($code) ?><div class="num"><?= htmlspecialchars($code) ?></div></div>
        <?php endif; ?>
    </div>
    <div class="foot"><?= htmlspecialchars($S['footer_text']) ?></div>
</div>
<?php return ob_get_clean();
    }
}

if (!function_exists('amc_sheet_back')) {
    function amc_sheet_back($m, $S) {
        $id    = (int)($m['member_id'] ?? 0);
        $code  = (string)($m['member_code'] ?? ($m['member_card_id'] ?? ($m['member_number'] ?? (string)$id)));
        $valid = date('d/m/Y', strtotime('+' . (int)$S['valid_years'] . ' years'));
        $issued= date('d/m/Y');
        $logo  = amc_logo_data($S);
        $sign  = amc_sign_data($S);
        $style = in_array($S['card_style'] ?? 'aurora', ['aurora','glass','dark'], true) ? $S['card_style'] : 'aurora';
        $fontC = 'f-' . ($S['card_font'] ?? 'modern');
        $terms = array_slice(preg_split('/\r?\n/', (string)($S['card_terms'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), 0, 6);
        $signTitle = trim((string)($S['sign_title'] ?? 'Kepala Perpustakaan'));
        $signName  = trim((string)($S['sign_name'] ?? ''));
        ob_start(); ?>
<div class="sheet st-<?= $style ?> <?= $fontC ?>" <?= amc_sheet_vars($S) ?>>
    <div class="fx fx-blob"></div><div class="fx fx-rings"></div><div class="fx fx-beam"></div><?php if ($style==='dark'): ?><div class="fx fx-weave"></div><?php endif; if ($S['show_holo']==='1'): ?><div class="holo"></div><?php endif; ?>
    <div class="stripe"></div>
    <div class="row" style="margin-top:1.4mm;">
        <div class="sigpanel">
            <?php if ($sign): ?><img src="<?= $sign ?>" alt=""><?php else: ?><div class="space"></div><?php endif; ?>
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
        <ol><?php foreach ($terms as $t): ?><li><?= htmlspecialchars($t) ?></li><?php endforeach; ?></ol>
    </div>
    <div class="row backfoot">
        <div class="brand">
            <div class="lg"><?php if ($logo): ?><img src="<?= $logo ?>" alt=""><?php else: ?><?= htmlspecialchars(amc_initials($S['library_name'])) ?><?php endif; ?></div>
            <div class="tx"><b><?= htmlspecialchars($S['library_name']) ?></b></div>
        </div>
        <span style="font-family:'Courier New',monospace;font-size:2.1mm;letter-spacing:.9mm;opacity:.9;"><?= htmlspecialchars($code) ?></span>
    </div>
</div>
<?php return ob_get_clean();
    }
}

if (!function_exists('amc_batch_members')) {
    function amc_batch_members($dbs, $filter) {
        $where = []; $params = []; $types = '';
        $ids = $filter['ids'] ?? null;
        if (!$ids && isset($_GET['ids']) && $_GET['ids'] !== '') {
            $ids = array_map('intval', explode(',', (string)$_GET['ids']));
        }
        if ($ids) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array)$ids))));
            if ($ids) $where[] = 'member_id IN (' . implode(',', $ids) . ')';
        }
        if (!empty($filter['type'])) { $where[] = 'member_type_id = ?'; $params[] = (int)$filter['type']; $types .= 'i'; }
        if (!empty($filter['year'])) { $where[] = '(member_since LIKE ? OR register_date LIKE ?)'; $y = $filter['year'] . '%'; $params[] = $y; $params[] = $y; $types .= 'ss'; }
        if (!empty($filter['q'])) { $where[] = '(member_name LIKE ? OR member_code LIKE ?)'; $q = '%' . $filter['q'] . '%'; $params[] = $q; $params[] = $q; $types .= 'ss'; }
        $sql = 'SELECT * FROM member' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY member_name ASC';
        if (!empty($filter['limit'])) { $sql .= ' LIMIT ' . (int)$filter['limit']; }
        if ($params) {
            $st = $dbs->prepare($sql);
            $st->bind_param($types, ...$params);
            $st->execute();
            return $st->get_result();
        }
        return $dbs->query($sql);
    }
}