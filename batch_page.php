<?php
global $dbs;
require_once __DIR__ . '/amc_lib.php';
require_once __DIR__ . '/migration.php';
run_awesome_card_migration();
$S = amc_settings();
$saved = isset($_GET['saved']);
$tpl = json_decode((string)($S['type_templates'] ?? ''), true);
if (!is_array($tpl)) $tpl = [];

$types = [];
try {
    $r = $dbs->query("SELECT member_type_id, member_type_name FROM member_type ORDER BY member_type_id");
    if ($r) { while ($t = $r->fetch_assoc()) $types[] = $t; }
} catch (\Throwable $e) { $types = []; }
?>
<script>
(function(){
    var chrome = document.querySelector('nav, aside, header, .sidebar, #sidebar, .menuBox');
    if (!chrome && window.top === window.self) { window.location.replace('index.php?mod=membership'); }
})();
</script>
<style>
.amc5,.amc5 *{box-sizing:border-box;}
.amc5{font-family:'Segoe UI',sans-serif;margin:10px 0 30px;}
.amc5-panel{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:22px;box-shadow:0 4px 18px rgba(15,23,42,.06);margin-bottom:20px;}
.amc5-head h2{font-size:22px;margin:0 0 6px;color:#0f172a;}
.amc5-head .sub{font-size:13px;color:#64748b;margin:0;}
.amc5-msg{background:#dcfce7;color:#166534;border:1px solid #86efac;padding:10px 14px;border-radius:10px;font-size:13px;margin-top:14px;}
.amc5-panel h3{font-size:14px;text-transform:uppercase;letter-spacing:1px;color:#475569;margin:0 0 14px;}
.amc5-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;}
.amc5-grid label{display:block;font-size:11px;font-weight:700;color:#475569;margin-bottom:5px;text-transform:uppercase;letter-spacing:.6px;}
.amc5-grid select,.amc5-grid input{width:100%;padding:9px 11px;border:1px solid #cbd5e1;border-radius:9px;font-size:13px;outline:none;background:#fff;color:#0f172a;}
.amc5-actions{display:flex;gap:10px;margin-top:18px;flex-wrap:wrap;}
.amc5-btn{padding:11px 22px;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;color:#fff;}
.amc5-btn.print{background:linear-gradient(90deg,#1e3a8a,#3b82f6);}
.amc5-btn.a4{background:linear-gradient(90deg,#0f766e,#14b8a6);}
.amc5-btn.pdf{background:linear-gradient(90deg,#b91c1c,#ef4444);}
.amc5-btn.save{background:linear-gradient(90deg,#334155,#64748b);}
.amc5-btn:hover{filter:brightness(1.1);}
.amc5-tpl{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;}
.amc5-tplcard{border:1px solid #e2e8f0;border-radius:12px;padding:14px;background:#f8fafc;}
.amc5-tplcard b{display:block;font-size:13px;color:#0f172a;margin-bottom:10px;}
.amc5-tplrow{display:flex;gap:8px;margin-bottom:8px;}
.amc5-tplrow input[type=color]{width:100%;height:32px;border:1px solid #cbd5e1;border-radius:7px;background:#fff;padding:2px;cursor:pointer;}
.amc5-tplrow select{width:100%;padding:6px;border:1px solid #cbd5e1;border-radius:7px;font-size:12px;background:#fff;color:#0f172a;}
.amc5-note{font-size:12px;color:#64748b;margin-top:12px;line-height:1.6;}
</style>

<div class="amc5">
    <div class="amc5-panel amc5-head">
        <h2>Produksi &amp; Pencetakan</h2>
        <p class="sub">Cetak massal seluruh kartu anggota, lembar A4 siap potong untuk percetakan, serta ekspor PDF berukuran kartu.</p>
        <?php if ($saved): ?><div class="amc5-msg">Template jenis keanggotaan berhasil disimpan.</div><?php endif; ?>
    </div>

    <form method="get" action="index.php" target="_blank" id="amc5form" class="amc5-panel">
        <input type="hidden" name="mod" value="membership">
        <input type="hidden" name="act" value="awesome_member_card">
        <input type="hidden" name="action" id="amc5action" value="batch_print">
        <input type="hidden" name="mode" id="amc5mode" value="cards">
        <h3>Penyaring Produksi</h3>
        <div class="amc5-grid">
            <div><label>Jenis Keanggotaan</label>
                <select name="type">
                    <option value="">Semua jenis</option>
                    <?php foreach ($types as $t): ?><option value="<?= (int)$t['member_type_id'] ?>"><?= htmlspecialchars($t['member_type_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label>Tahun Pendaftaran</label>
                <select name="year">
                    <option value="">Semua tahun</option>
                    <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 10; $y--): ?><option value="<?= $y ?>"><?= $y ?></option><?php endfor; ?>
                </select>
            </div>
            <div><label>Cari Nama / Kode</label><input type="text" name="q" placeholder="kosongkan untuk semua"></div>
            <div><label>Batas Jumlah</label>
                <select name="limit">
                    <option value="">Tanpa batas</option>
                    <option value="50">50 kartu</option>
                    <option value="100">100 kartu</option>
                    <option value="500">500 kartu</option>
                </select>
            </div>
        </div>
        <div class="amc5-actions">
            <button type="button" class="amc5-btn print" onclick="amc5go('batch_print','cards')">Cetak Hemat (3 Anggota/Lembar)</button>
            <button type="button" class="amc5-btn print" onclick="amc5go('batch_print','cr80')">CR80 (1 Kartu/Halaman)</button>
            <button type="button" class="amc5-btn a4" onclick="amc5go('batch_print','a4front')">Lembar A4 &mdash; Sisi Depan</button>
            <button type="button" class="amc5-btn a4" onclick="amc5go('batch_print','a4back')">Lembar A4 &mdash; Sisi Belakang</button>
            <button type="button" class="amc5-btn a4" onclick="amc5go('batch_print','fold')">Lipat Presisi (A4)</button>
            <button type="button" class="amc5-btn pdf" onclick="amc5go('batch_pdf','cards')">Ekspor PDF</button>
        </div>
        <p class="amc5-note"><b>Cetak Hemat</b>: maksimal 3 anggota per lembar A4; sisi depan dan belakang berdampingan dengan garis lipat vertikal &mdash; potong mengikuti tanda L, lalu lipat paruh kanan ke belakang sehingga kedua sisi berhimpit presisi. <b>CR80</b>: halaman berukuran kartu untuk printer kartu bolak-balik otomatis. <b>Lembar A4</b>: 8 sisi per lembar dengan tanda potong L; cetak sisi depan terlebih dahulu, lalu balik tumpukan dan cetak sisi belakang dengan urutan sama. <b>Lipat Presisi</b>: pasangan 85,6 x 108 mm dengan garis lipat horizontal. <b>Ekspor PDF</b>: berkas multi-halaman berukuran kartu CR80.</p>
    </form>

    <form method="post" action="index.php?mod=membership&act=awesome_member_card&action=batch_save" class="amc5-panel">
        <h3>Template per Jenis Keanggotaan</h3>
        <?php if ($types): ?>
        <div class="amc5-tpl">
            <?php foreach ($types as $t): $tid = (int)$t['member_type_id']; $cfg = $tpl[(string)$tid] ?? []; ?>
            <div class="amc5-tplcard">
                <b><?= htmlspecialchars($t['member_type_name']) ?></b>
                <div class="amc5-tplrow">
                    <input type="color" name="tpl[<?= $tid ?>][p]" value="<?= htmlspecialchars($cfg['primary_color'] ?? $S['primary_color']) ?>">
                    <input type="color" name="tpl[<?= $tid ?>][s]" value="<?= htmlspecialchars($cfg['secondary_color'] ?? $S['secondary_color']) ?>">
                    <input type="color" name="tpl[<?= $tid ?>][a]" value="<?= htmlspecialchars($cfg['accent_color'] ?? $S['accent_color']) ?>">
                </div>
                <div class="amc5-tplrow">
                    <select name="tpl[<?= $tid ?>][style]">
                        <option value="aurora" <?= ($cfg['card_style'] ?? 'aurora')==='aurora'?'selected':'' ?>>Aurora</option>
                        <option value="glass"  <?= ($cfg['card_style'] ?? '')==='glass'?'selected':'' ?>>Glass</option>
                        <option value="dark"   <?= ($cfg['card_style'] ?? '')==='dark'?'selected':'' ?>>Dark Carbon</option>
                    </select>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="amc5-actions"><button type="submit" class="amc5-btn save">Simpan Template Jenis</button></div>
        <p class="amc5-note">Template yang disimpan diterapkan otomatis pada cetak massal, lembar A4, dan ekspor PDF sesuai jenis keanggotaan masing-masing anggota.</p>
        <?php else: ?>
        <p class="amc5-note">Belum ada jenis keanggotaan terdaftar. Tambahkan melalui menu <b>Tipe Keanggotaan</b> untuk mengaktifkan template per jenis.</p>
        <?php endif; ?>
    </form>
</div>
<script>
function amc5go(action, mode){
    document.getElementById('amc5action').value = action;
    document.getElementById('amc5mode').value = mode;
    document.getElementById('amc5form').submit();
}
</script>