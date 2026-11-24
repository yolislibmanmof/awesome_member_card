<?php
/**
 * Awesome Member Card — Panel ulang tahun anggota (final 1.6.0)
 * Perbaikan: penyaringan di SQL (statistik akurat, hemat memori), 29 Februari aman,
 *            clipboard fallback untuk HTTP LAN, CSRF email, tombol wa.me,
 *            bulan Indonesia, kirim massal "Hari Ini".
 */
defined('INDEX_AUTH') OR die('Direct access not allowed!');
global $dbs;
require_once __DIR__ . '/amc_lib.php';

$S = amc_settings();

/* ==== CSRF untuk aksi email ==== */
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (empty($_SESSION['amc_csrf_token'])) { $_SESSION['amc_csrf_token'] = bin2hex(random_bytes(32)); }
$csrf_token = (string)$_SESSION['amc_csrf_token'];

/* ==== Basis situs absolut ==== */
if (!function_exists('amc_site_base')) {
    function amc_site_base() {
        $base = defined('SWB') ? SWB : '/';
        if (preg_match('#^https?://#i', $base)) return rtrim($base, '/') . '/';
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
              || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = is_string($base) && $base !== '' && $base[0] === '/' ? rtrim($base, '/') : '';
        return ($https ? 'https://' : 'http://') . $host . $path . '/';
    }
}
if (!function_exists('amc_bulan_id')) {
    function amc_bulan_id($n) {
        $b = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        return $b[(int)$n] ?? '';
    }
}
if (!function_exists('amc_parse_birth')) {
    function amc_parse_birth($raw) {
        $raw = trim((string)$raw);
        if ($raw === '' || strpos($raw, '0000') === 0) return null;
        foreach (['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'd-m-Y'] as $fmt) {
            $d = DateTime::createFromFormat($fmt, $raw);
            if ($d === false) continue;
            $e = DateTime::getLastErrors();
            if (is_array($e) && (($e['warning_count'] ?? 0) > 0 || ($e['error_count'] ?? 0) > 0)) continue;
            return $d;
        }
        return null;
    }
}

/* ==== Deteksi kolom tanggal lahir (tahan perbedaan skema) ==== */
$col = null;
foreach (['birth_date', 'member_birth_date'] as $c) {
    try {
        $r = $dbs->query("SHOW COLUMNS FROM member LIKE '" . $c . "'");
        if ($r && $r->num_rows > 0) { $col = $c; break; }
    } catch (\Throwable $e) {}
}

/* ==== Statistik akurat lewat SQL (bukan sampel 5000) ==== */
$total = 0; $emptyBirth = 0;
try {
    if ($col) {
        $r = $dbs->query("SELECT COUNT(*) AS total, SUM(CASE WHEN `$col` IS NULL OR CAST(`$col` AS CHAR) LIKE '0000%' THEN 1 ELSE 0 END) AS kosong FROM member");
    } else {
        $r = $dbs->query("SELECT COUNT(*) AS total, 0 AS kosong FROM member");
    }
    if ($r && ($row = $r->fetch_assoc())) { $total = (int)$row['total']; $emptyBirth = (int)$row['kosong']; }
} catch (\Throwable $e) {}
$filled = max(0, $total - $emptyBirth);

/* ==== Jendela tanggal: kemarin (-1) s/d +30 hari ==== */
$today = new DateTime('today');
$window = [];
for ($i = -1; $i <= 30; $i++) {
    $dt  = (clone $today)->modify(($i >= 0 ? '+' : '') . $i . ' day');
    $key = $dt->format('m-d');
    if (!isset($window[$key])) $window[$key] = ['dt' => $dt, 'offset' => $i];
}
/* 29 Februari pada tahun non-kabis dirayakan 28 Februari */
if (isset($window['02-28']) && !checkdate(2, 29, (int)$window['02-28']['dt']->format('Y'))) {
    $window['02-29'] = $window['02-28'];
}

/* ==== Ambil HANYA anggota yang relevan ==== */
$rows = []; $sqlUsed = false;
if ($col) {
    try {
        $keys = array_keys($window);
        $in   = implode(',', array_fill(0, count($keys), '?'));
        $sql  = "SELECT *, DATE_FORMAT(`$col`,'%m-%d') AS amc_md, DATE_FORMAT(`$col`,'%Y-%m-%d') AS amc_bd
                 FROM member
                 WHERE `$col` IS NOT NULL AND CAST(`$col` AS CHAR) NOT LIKE '0000%'
                   AND DATE_FORMAT(`$col`,'%m-%d') IN ($in)
                 ORDER BY member_name ASC LIMIT 3000";
        $st = $dbs->prepare($sql);
        if ($st) {
            $types = str_repeat('s', count($keys));
            $refs  = []; $refs[] = &$types;
            foreach ($keys as $k => $v) { $refs[] = &$keys[$k]; }
            call_user_func_array([$st, 'bind_param'], $refs);
            $st->execute();
            $res = $st->get_result();
            while ($m = $res->fetch_assoc()) $rows[] = $m;
            $st->close();
            $sqlUsed = true;
        }
    } catch (\Throwable $e) { $sqlUsed = false; }
}
/* Cadangan: skema lama / query gagal -> pemindaian PHP terbatas */
if (!$sqlUsed) {
    try {
        $res = $dbs->query("SELECT * FROM member LIMIT 5000");
        if ($res) { while ($m = $res->fetch_assoc()) $rows[] = $m; }
    } catch (\Throwable $e) { $rows = []; }
    if ($total === 0) { $total = count($rows); }
}

/* ==== Klasifikasi ==== */
$items = [];
foreach ($rows as $m) {
    if ($sqlUsed) {
        $md = (string)($m['amc_md'] ?? '');
        $d  = amc_parse_birth((string)($m['amc_bd'] ?? ''));
    } else {
        $d  = amc_parse_birth($m['birth_date'] ?? ($m['member_birth_date'] ?? null));
        $md = $d ? $d->format('m-d') : '';
    }
    if (!$d || $md === '' || !isset($window[$md])) continue;
    $off  = (int)$window[$md]['offset'];
    $when = null; $order = 99;
    if ($off === -1)     { $when = 'Kemarin';  $order = 1; }
    elseif ($off === 0)  { $when = 'Hari Ini'; $order = 2; }
    elseif ($off === 1)  { $when = 'Besok';    $order = 3; }
    $items[] = ['m' => $m, 'birth' => $d, 'when' => $when, 'order' => $order, 'days' => $off];
}
$three = array_values(array_filter($items, fn($i) => $i['when'] !== null));
usort($three, fn($a, $b) => [$a['order'], $a['m']['member_name'] ?? ''] <=> [$b['order'], $b['m']['member_name'] ?? '']);
$upcoming = array_values(array_filter($items, fn($i) => $i['when'] === null && $i['days'] >= 2 && $i['days'] <= 30));
usort($upcoming, fn($a, $b) => [$a['days'], $a['m']['member_name'] ?? ''] <=> [$b['days'], $b['m']['member_name'] ?? '']);
$counts = ['Kemarin' => 0, 'Hari Ini' => 0, 'Besok' => 0];
foreach ($three as $i) $counts[$i['when']]++;
$todayIds = [];
foreach ($three as $i) if ($i['when'] === 'Hari Ini') $todayIds[] = (int)($i['m']['member_id'] ?? 0);

$wishBase = amc_site_base() . 'index.php?p=amc-birthday-wish&name=';

/* ==== Kartu anggota ==== */
$renderCard = function ($i, $extraLabel = '') use ($wishBase, $S, $today) {
    $m = $i['m'];
    $id    = (int)($m['member_id'] ?? 0);
    $name  = (string)($m['member_name'] ?? 'Anggota');
    $code  = (string)($m['member_code'] ?? '');
    $email = trim((string)($m['member_email'] ?? ''));
    $phone = trim((string)($m['member_phone'] ?? ''));
    $inst  = trim((string)($m['inst_name'] ?? ''));
    $by    = (int)$i['birth']->format('Y');
    $age   = ($by > 1900) ? ((int)$today->format('Y') - $by) : 0;
    $birthTxt = (int)$i['birth']->format('j') . ' ' . amc_bulan_id($i['birth']->format('n')) . ' ' . $by;
    $cls      = $i['when'] ? strtolower(str_replace(' ', '', $i['when'])) : 'upcoming';
    $wishUrl  = $wishBase . rawurlencode(mb_substr($name, 0, 80));
    $waText   = "Selamat ulang tahun, " . $name . "! 🎂🎉\n\nSemoga panjang umur, sehat selalu, dan sukses dalam segala hal.\n\nDari: " . $S['library_name'] . "\n" . $wishUrl;

    /* Normalisasi nomor WhatsApp ke format internasional */
    $waNum = preg_replace('/\D+/', '', $phone);
    if ($waNum !== '') {
        if (strpos($waNum, '0') === 0)      $waNum = '62' . substr($waNum, 1);
        elseif (strpos($waNum, '8') === 0)  $waNum = '62' . $waNum;
        if (strlen($waNum) < 9)             $waNum = '';
    }
    $waLink = $waNum !== '' ? 'https://wa.me/' . $waNum . '?text=' . rawurlencode($waText) : '';
    $search = strtolower($name . ' ' . $code . ' ' . $phone . ' ' . $email . ' ' . $i['birth']->format('Y-m-d') . ' ' . $birthTxt);
    ?>
    <div class="bcard <?= $cls ?>" data-search="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
        <div class="binfo">
            <?php if ($i['when']): ?><span class="when w-<?= $cls ?>"><?= $i['when'] ?></span>
            <?php else: ?><span class="when w-up"><?= htmlspecialchars($extraLabel !== '' ? $extraLabel : ('Dalam ' . $i['days'] . ' hari')) ?></span><?php endif; ?>
            <?php if ($age > 0): ?><span class="bage">Usia ke-<?= $age ?></span><?php endif; ?>
            <h4><?= htmlspecialchars($name) ?></h4>
            <p><strong>No. Anggota:</strong> <?= htmlspecialchars($code) ?> &bull; <strong>Lahir:</strong> <?= htmlspecialchars($birthTxt) ?></p>
            <?php if ($inst !== ''): ?><p><strong>Institusi:</strong> <?= htmlspecialchars($inst) ?></p><?php endif; ?>
            <?php if ($phone !== ''): ?><p><strong>Telepon:</strong> <?= htmlspecialchars($phone) ?></p><?php endif; ?>
            <?php if ($email !== ''): ?><p><strong>Email:</strong> <?= htmlspecialchars($email) ?></p><?php endif; ?>
        </div>
        <div class="bact">
            <button class="bbtn url" type="button" onclick="bcopy('<?= htmlspecialchars($wishUrl, ENT_QUOTES) ?>','URL halaman ucapan disalin')">📋 Copy URL</button>
            <a class="bbtn view" target="_blank" rel="noopener" href="<?= htmlspecialchars($wishUrl) ?>">🎉 Lihat Ucapan</a>
            <button class="bbtn wa" type="button" onclick="bcopy(<?= htmlspecialchars(json_encode($waText, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>,'Pesan WhatsApp disalin')">💬 Copy WA</button>
            <?php if ($waLink !== ''): ?><a class="bbtn wa2" target="_blank" rel="noopener" href="<?= htmlspecialchars($waLink) ?>">📲 Kirim WA</a><?php endif; ?>
            <?php if ($email !== ''): ?><button class="bbtn em" type="button" data-mid="<?= $id ?>" data-name="<?= htmlspecialchars($name, ENT_QUOTES) ?>" onclick="bmail(<?= $id ?>,this.dataset.name,0,this)">📧 Kirim Email</button><?php endif; ?>
        </div>
    </div>
    <?php
};
?>
<style>
.bwrap{font-family:'Segoe UI',sans-serif;margin:10px 0 30px;}
.bhero{position:relative;overflow:hidden;border-radius:20px;padding:28px 30px;color:#fff;background:linear-gradient(120deg,#ec4899,#f472b6 55%,#fbbf24);}
.bhero h2{margin:0 0 6px;font-size:26px;}
.bhero p{margin:0;opacity:.92;font-size:13px;}
.bstats{display:flex;gap:10px;margin-top:18px;flex-wrap:wrap;}
.bchip{background:rgba(255,255,255,.17);border:1px solid rgba(255,255,255,.32);border-radius:12px;padding:9px 14px;min-width:104px;}
.bchip b{display:block;font-size:19px;}
.bchip span{font-size:10px;opacity:.9;text-transform:uppercase;letter-spacing:1px;}
.bpanel{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:20px;margin-top:18px;}
.bpanel h3{font-size:14px;text-transform:uppercase;letter-spacing:1px;color:#475569;margin:0 0 12px;}
.btools{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px;}
.bsearch{flex:1;min-width:220px;padding:10px 14px;border-radius:10px;border:1px solid #cbd5e1;font-size:13px;outline:none;}
.bsearch:focus{border-color:#ec4899;box-shadow:0 0 0 3px rgba(236,72,153,.18);}
.bbulk{padding:10px 16px;border:none;border-radius:10px;background:linear-gradient(90deg,#1d4ed8,#3b82f6);color:#fff;font-size:12.5px;font-weight:700;cursor:pointer;}
.bbulk:disabled{opacity:.6;cursor:wait;}
.bcount{font-size:12px;color:#64748b;margin:0 0 12px;}
.blist{display:grid;gap:10px;}
.bcard{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px;display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;}
.bcard.hariini{background:linear-gradient(135deg,#fef3c7,#fde68a);border-color:#f59e0b;}
.bcard.besok{background:linear-gradient(135deg,#dbeafe,#bfdbfe);border-color:#3b82f6;}
.bcard.kemarin{background:#f1f5f9;border-color:#cbd5e1;}
.bcard.upcoming{background:#fdf2f8;border-color:#f9a8d4;}
.binfo{flex:1;min-width:220px;}
.binfo h4{margin:4px 0;font-size:15px;color:#0f172a;}
.binfo p{margin:2px 0;font-size:12px;color:#64748b;}
.when{display:inline-block;padding:3px 10px;border-radius:20px;font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:#fff;}
.bage{display:inline-block;margin-left:6px;padding:3px 9px;border-radius:20px;font-size:10.5px;font-weight:700;background:rgba(15,23,42,.12);color:#334155;}
.w-hariini{background:#f59e0b;} .w-besok{background:#3b82f6;} .w-kemarin{background:#64748b;} .w-up{background:#ec4899;}
.bact{display:flex;gap:8px;flex-wrap:wrap;}
.bbtn{padding:8px 13px;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;color:#fff;}
.bbtn.url{background:#0f172a;} .bbtn.wa{background:#25d366;} .bbtn.wa2{background:#128c7e;text-decoration:none;display:inline-block;}
.bbtn.em{background:#3b82f6;} .bbtn.em.ok{background:#16a34a;} .bbtn.em.no{background:#b91c1c;}
.bbtn.view{background:linear-gradient(135deg,#ec4899,#f59e0b);text-decoration:none;display:inline-block;}
.bbtn:hover{filter:brightness(1.12);}
.bbtn:disabled{opacity:.65;cursor:wait;}
.bempty{padding:26px;text-align:center;color:#64748b;background:#f1f5f9;border-radius:12px;font-size:13px;line-height:1.7;}
.bwarn{background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;border-radius:12px;padding:14px 16px;font-size:13px;line-height:1.7;margin-bottom:14px;}
.bnote{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;border-radius:12px;padding:12px 15px;font-size:12.5px;line-height:1.7;margin-top:14px;}
.btoast{position:fixed;top:20px;right:20px;max-width:340px;background:#0f172a;color:#fff;padding:12px 18px;border-radius:10px;box-shadow:0 8px 20px rgba(0,0,0,.25);z-index:9999;display:none;font-size:13px;line-height:1.6;}
.btoast.show{display:block;}
.btoast.bad{background:#991b1b;}
</style>

<div class="bwrap">
    <div class="bhero">
        <h2>🎂 Ulang Tahun Anggota</h2>
        <p><?= htmlspecialchars($S['library_name']) ?> &mdash; rayakan hari spesial anggota perpustakaan Anda</p>
        <div class="bstats">
            <div class="bchip"><b><?= (int)$counts['Hari Ini'] ?></b><span>Hari Ini</span></div>
            <div class="bchip"><b><?= (int)$counts['Besok'] ?></b><span>Besok</span></div>
            <div class="bchip"><b><?= (int)$counts['Kemarin'] ?></b><span>Kemarin</span></div>
            <div class="bchip"><b><?= count($upcoming) ?></b><span>30 Hari ke Depan</span></div>
            <div class="bchip"><b><?= number_format($filled) ?></b><span>Tgl Lahir Terisi</span></div>
            <div class="bchip"><b><?= number_format($emptyBirth) ?></b><span>Tgl Lahir Kosong</span></div>
            <div class="bchip"><b><?= number_format($total) ?></b><span>Total Anggota</span></div>
        </div>
    </div>

    <?php if (!$col): ?>
    <div class="bpanel"><div class="bwarn"><strong>Kolom tanggal lahir tidak ditemukan pada tabel anggota.</strong><br>Periksa struktur tabel <code>member</code> (kolom <em>birth_date</em>).</div></div>
    <?php elseif ($filled === 0): ?>
    <div class="bpanel"><div class="bwarn">
        <strong>Kolom tanggal lahir belum terisi pada seluruh <?= number_format($total) ?> anggota.</strong><br>
        Isi melalui <strong>Keanggotaan &rarr; Tambah/Ubah Anggota</strong> (kolom <em>Birth Date</em>), atau perbarui lewat <strong>Impor Data Anggota</strong>.
    </div></div>
    <?php endif; ?>

    <div class="bpanel">
        <h3>Kemarin, Hari Ini &amp; Besok</h3>
        <div class="btools">
            <input type="text" class="bsearch" id="bq" placeholder="Cari nama, nomor anggota, telepon, email, atau tanggal lahir..." onkeyup="bfilter(this.value)">
            <?php if ($todayIds): ?><button type="button" class="bbulk" id="bbulk" onclick="bmailAll()">📧 Kirim Email Semua (Hari Ini: <?= count($todayIds) ?>)</button><?php endif; ?>
        </div>
        <p class="bcount" id="bcount"></p>
        <?php if ($three): ?>
        <div class="blist" id="bl1"><?php foreach ($three as $i) $renderCard($i); ?></div>
        <?php else: ?>
        <div class="bempty">Tidak ada anggota yang berulang tahun kemarin, hari ini, atau besok.<br>Hal ini wajar bila tanggal lahir tersebar sepanjang tahun &mdash; lihat seksi <strong>30 Hari ke Depan</strong>.</div>
        <?php endif; ?>
    </div>

    <div class="bpanel">
        <h3>Ulang Tahun 30 Hari ke Depan</h3>
        <?php if ($upcoming): ?>
        <div class="blist" id="bl2"><?php foreach ($upcoming as $i) $renderCard($i, 'Dalam ' . $i['days'] . ' hari'); ?></div>
        <?php else: ?>
        <div class="bempty">Belum ada ulang tahun dalam 30 hari ke depan di antara anggota yang tanggal lahirnya terisi.</div>
        <?php endif; ?>
        <div class="bnote">
            <b>Catatan operasional:</b> tombol <b>Kirim WA</b> muncul bila nomor telepon anggota terisi. Tanggal <b>29 Februari</b> dirayakan pada <b>28 Februari</b> di tahun non-kabis.
            <?php if (!$sqlUsed && $col): ?><br><b>Mode cadangan aktif:</b> pemindaian terbatas 5000 anggota (struktur tabel lama).<?php endif; ?>
        </div>
    </div>
</div>

<div class="btoast" id="btoast"></div>
<script>
var AMC_CSRF   = <?= json_encode($csrf_token) ?>;
var AMC_TODAY  = <?= json_encode(array_values(array_filter($todayIds))) ?>;
var AMC_MAILURL= 'index.php?mod=membership&act=awesome_member_card&action=birthday_email';

function btoast(msg, bad, ms){
    var t = document.getElementById('btoast');
    t.textContent = msg; t.className = 'btoast show' + (bad ? ' bad' : '');
    clearTimeout(t._tm); t._tm = setTimeout(function(){ t.className = 'btoast'; }, ms || (bad ? 7000 : 3000));
}

/* ==== Clipboard: HTTPS/localhost pakai API modern, HTTP LAN pakai fallback ==== */
function bcopy(txt, msg){
    var done = function(){ btoast('✓ ' + msg); };
    var fail = function(){ btoast('✗ Gagal menyalin. Blok teks lalu salin manual (Ctrl+C).', true); };
    var legacy = function(){
        try {
            var ta = document.createElement('textarea');
            ta.value = txt; ta.setAttribute('readonly', '');
            ta.style.position = 'fixed'; ta.style.top = '-1000px'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select(); ta.setSelectionRange(0, ta.value.length);
            var ok = document.execCommand('copy');
            document.body.removeChild(ta);
            ok ? done() : fail();
        } catch (e) { fail(); }
    };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(txt).then(done).catch(legacy);
    } else { legacy(); }
}

/* ==== Kirim email dengan CSRF + penanganan duplikat ==== */
function bmail(id, name, force, btn, silent){
    if (!silent && !confirm('Kirim ucapan ulang tahun via email ke ' + name + '?')) return Promise.resolve(false);
    if (btn) { btn.disabled = true; var old = btn.textContent; btn.textContent = 'Mengirim...'; }
    var body = 'member_id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(AMC_CSRF) + (force ? '&force=1' : '');
    return fetch(AMC_MAILURL, { method:'POST', credentials:'same-origin',
        headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'}, body: body })
    .then(function(r){ return r.text(); })
    .then(function(t){
        var d = null; try { d = JSON.parse(t); } catch(e){}
        if (btn) { btn.disabled = false; btn.textContent = old; }
        if (!d) { if (!silent) btoast('✗ Respons server tidak valid. Periksa log PHP.', true); return false; }
        if (d.success) {
            if (btn) { btn.classList.add('ok'); btn.textContent = '✓ Terkirim'; }
            if (!silent) btoast('✓ Email terkirim ke ' + name + (d.via ? ' (via ' + d.via + ')' : ''));
            return true;
        }
        if (d.duplicate && !force) {
            if (confirm((d.error || 'Sudah pernah dikirim.') + '\n\nKirim ulang sekarang?')) return bmail(id, name, 1, btn, true);
            return false;
        }
        if (btn) btn.classList.add('no');
        if (!silent) btoast('✗ Gagal: ' + (d.error || 'kesalahan tidak diketahui'), true);
        return false;
    })
    .catch(function(e){
        if (btn) { btn.disabled = false; btn.textContent = old; btn.classList.add('no'); }
        if (!silent) btoast('✗ Error jaringan: ' + e.message, true);
        return false;
    });
}

/* ==== Kirim massal berurutan (maks 50, jeda 600ms) ==== */
function bmailAll(){
    var list = (AMC_TODAY || []).slice(0, 50);
    if (!list.length) { btoast('Tidak ada anggota berulang tahun hari ini.', true); return; }
    if (!confirm('Kirim ucapan email ke ' + list.length + ' anggota yang berulang tahun HARI INI?\nProses berjalan berurutan, mohon jangan tutup halaman.')) return;
    var btn = document.getElementById('bbulk'); if (btn) btn.disabled = true;
    var i = 0, ok = 0, no = 0;
    var step = function(){
        if (i >= list.length) {
            if (btn) { btn.disabled = false; btn.textContent = 'Selesai: ' + ok + ' terkirim, ' + no + ' gagal'; }
            btoast('Selesai. Terkirim: ' + ok + ' | Gagal: ' + no, no > 0, 8000);
            return;
        }
        var id = list[i++];
        var b = document.querySelector('.bbtn.em[data-mid="' + id + '"]');
        bmail(id, b ? b.dataset.name : ('ID ' + id), 0, b, true).then(function(r){ r ? ok++ : no++; setTimeout(step, 600); });
    };
    step();
}

function bfilter(q){
    q = (q || '').toLowerCase();
    var vis = 0, all = document.querySelectorAll('.bcard');
    all.forEach(function(c){
        var show = (c.getAttribute('data-search') || '').indexOf(q) !== -1;
        c.style.display = show ? '' : 'none';
        if (show) vis++;
    });
    var el = document.getElementById('bcount');
    if (el) el.textContent = 'Menampilkan ' + vis + ' dari ' + all.length + ' anggota dalam panel ini.';
}
bfilter('');
</script>