<?php
global $dbs;
require_once __DIR__ . '/amc_lib.php';
require_once __DIR__ . '/migration.php';
run_awesome_card_migration();
$S = amc_settings();

try { $total = (int)($dbs->query("SELECT COUNT(*) c FROM member")->fetch_assoc()['c'] ?? 0); } catch (\Throwable $e) { $total = 0; }
try { $members = $dbs->query("SELECT * FROM member ORDER BY member_id DESC LIMIT 2000"); } catch (\Throwable $e) { $members = false; }

$rows = [];
if ($members) { while ($m = $members->fetch_assoc()) $rows[] = $m; }
$newSince = date('Y-m-d', strtotime('-30 days'));
$newCount = 0;
foreach ($rows as $r) {
    $d = trim((string)($r['member_since'] ?? ($r['register_date'] ?? '')));
    if ($d !== '' && strpos($d, '0000') !== 0 && $d >= $newSince) $newCount++;
}
$shown = count($rows);
?>
<style>
.amc2{--p:<?= $S['primary_color'] ?>;--s:<?= $S['secondary_color'] ?>;--a:<?= $S['accent_color'] ?>;font-family:'Segoe UI',sans-serif;margin:10px 0 90px;}
.amc2-hero{position:relative;overflow:hidden;border-radius:20px;padding:30px 32px;color:#fff;background:linear-gradient(120deg,var(--p),var(--s) 55%,var(--a));}
.amc2-hero::before{content:'';position:absolute;inset:0;background:radial-gradient(620px 220px at 85% -20%,rgba(255,255,255,.38),transparent 60%),radial-gradient(420px 200px at 8% 120%,rgba(255,255,255,.2),transparent 60%);}
.amc2-hero::after{content:'';position:absolute;width:220px;height:220px;border-radius:50%;right:-60px;top:-90px;background:rgba(255,255,255,.14);filter:blur(2px);}
.amc2-hero h2{position:relative;margin:0 0 6px;font-size:27px;letter-spacing:.5px;text-shadow:0 2px 12px rgba(0,0,0,.25);}
.amc2-hero p{position:relative;margin:0;opacity:.92;font-size:13px;}
.amc2-stats{position:relative;display:flex;gap:12px;margin-top:20px;flex-wrap:wrap;}
.amc2-chip{background:rgba(255,255,255,.17);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.32);border-radius:12px;padding:10px 16px;min-width:118px;}
.amc2-chip b{display:block;font-size:20px;}
.amc2-chip span{font-size:10.5px;opacity:.88;text-transform:uppercase;letter-spacing:1px;}
.amc2-bar{display:flex;align-items:center;gap:12px;margin:22px 0 6px;flex-wrap:wrap;}
.amc2-bar input[type=text]{flex:1;min-width:220px;padding:11px 16px;border-radius:12px;border:1px solid #cbd5e1;font-size:14px;outline:none;background:#fff;}
.amc2-bar input[type=text]:focus{border-color:var(--s);box-shadow:0 0 0 3px rgba(59,130,246,.2);}
.amc2-bar select{padding:11px 14px;border-radius:12px;border:1px solid #cbd5e1;font-size:13px;background:#fff;outline:none;}
.amc2-bar button{padding:11px 16px;border-radius:12px;border:1px solid #cbd5e1;background:#fff;font-size:13px;font-weight:600;cursor:pointer;color:#334155;}
.amc2-bar button:hover{border-color:var(--s);color:var(--s);}
.amc2-count{font-size:12px;color:#64748b;margin:0 0 16px;}
.amc2-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(235px,1fr));gap:16px;}
.amc2-card{position:relative;border-radius:16px;padding:16px;color:#fff;background:linear-gradient(135deg,var(--p),var(--s));overflow:hidden;transition:transform .22s,box-shadow .22s,outline-color .22s;outline:3px solid transparent;outline-offset:2px;}
.amc2-card:hover{transform:translateY(-5px);box-shadow:0 16px 36px rgba(30,58,138,.35);}
.amc2-card.sel{outline-color:var(--a);box-shadow:0 14px 34px rgba(30,58,138,.45);}
.amc2-card::after{content:'';position:absolute;top:-60%;right:-60%;width:120%;height:120%;background:radial-gradient(circle,rgba(255,255,255,.16),transparent 65%);pointer-events:none;}
.amc2-check{position:absolute;top:10px;right:10px;z-index:5;cursor:pointer;}
.amc2-check input{position:absolute;opacity:0;width:0;height:0;}
.amc2-check span{display:flex;width:22px;height:22px;border-radius:7px;border:2px solid rgba(255,255,255,.75);background:rgba(255,255,255,.15);align-items:center;justify-content:center;font-size:13px;font-weight:900;color:transparent;transition:all .15s;}
.amc2-check input:checked + span{background:var(--a);border-color:var(--a);color:#1e293b;}
.amc2-check input:checked + span::after{content:'\2713';}
.amc2-new{position:absolute;top:12px;left:12px;z-index:4;background:#22c55e;color:#fff;font-size:9px;font-weight:800;letter-spacing:1px;padding:3px 8px;border-radius:20px;box-shadow:0 3px 8px rgba(0,0,0,.25);}
.amc2-ava{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,var(--a),#fff);color:var(--p);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px;margin:14px 0 10px;border:2px solid rgba(255,255,255,.75);}
.amc2-ava img{width:100%;height:100%;border-radius:50%;object-fit:cover;}
.amc2-code{font-family:'Courier New',monospace;font-size:10px;letter-spacing:1.5px;opacity:.85;word-break:break-all;}
.amc2-name{display:block;font-size:15px;font-weight:700;text-transform:uppercase;margin:3px 0 12px;}
.amc2-btn{position:relative;display:block;text-align:center;background:#fff;color:var(--p);padding:8px;border-radius:9px;text-decoration:none;font-size:13px;font-weight:700;}
.amc2-btn:hover{filter:brightness(.96);}
.amc2-empty{padding:30px;text-align:center;color:#64748b;background:#f1f5f9;border-radius:14px;}
.amc2-fab{position:fixed;left:50%;bottom:22px;transform:translateX(-50%) translateY(20px);display:none;align-items:center;gap:10px;background:rgba(15,23,42,.94);backdrop-filter:blur(10px);padding:12px 18px;border-radius:16px;box-shadow:0 18px 44px rgba(15,23,42,.5);z-index:999;opacity:0;transition:all .25s;}
.amc2-fab.show{display:flex;opacity:1;transform:translateX(-50%) translateY(0);}
.amc2-fab span{color:#e2e8f0;font-size:13px;font-weight:700;margin-right:4px;white-space:nowrap;}
.amc2-fab button{padding:9px 14px;border:none;border-radius:10px;font-size:12.5px;font-weight:700;cursor:pointer;color:#fff;white-space:nowrap;}
.amc2-fab .f1{background:linear-gradient(90deg,#1e3a8a,#3b82f6);}
.amc2-fab .f2{background:linear-gradient(90deg,#0f766e,#14b8a6);}
.amc2-fab .f3{background:linear-gradient(90deg,#b91c1c,#ef4444);}
.amc2-fab .fx{background:#334155;}
</style>

<div class="amc2">
    <div class="amc2-hero">
        <h2>Kartu Anggota Keren</h2>
        <p><?= htmlspecialchars($S['library_name']) ?> &mdash; <?= htmlspecialchars($S['card_subtitle']) ?></p>
        <div class="amc2-stats">
            <div class="amc2-chip"><b><?= number_format($total) ?></b><span>Total Anggota</span></div>
            <div class="amc2-chip"><b><?= number_format($newCount) ?></b><span>Baru 30 Hari</span></div>
            <div class="amc2-chip"><b><?= number_format($shown) ?></b><span>Kartu Dimuat</span></div>
            <div class="amc2-chip"><b><?= ucfirst(htmlspecialchars($S['card_style'])) ?></b><span>Gaya Kartu</span></div>
        </div>
    </div>

    <div class="amc2-bar">
        <input id="amc2q" type="text" placeholder="Filter instan: ketik nama atau kode anggota..." onkeyup="amc2filter(this.value)">
        <select id="amc2sort" onchange="amc2sort(this.value)">
            <option value="new">Urutan: Anggota Terbaru</option>
            <option value="name">Urutan: Nama A&ndash;Z</option>
        </select>
        <button type="button" onclick="amc2all()">Pilih Semua Tertampil</button>
    </div>
    <p class="amc2-count" id="amc2count"></p>

    <div class="amc2-grid" id="amc2grid">
    <?php if ($shown > 0): ?>
        <?php foreach ($rows as $m):
            $id   = (int)($m['member_id'] ?? 0);
            $code = $m['member_code'] ?? ($m['member_card_id'] ?? ($m['member_number'] ?? (string)$id));
            $name = $m['member_name'] ?? ($m['member_full_name'] ?? 'Anggota');
            $photo = ($S['show_photo'] === '1') ? amc_member_photo($m) : null;
            $d = trim((string)($m['member_since'] ?? ($m['register_date'] ?? '')));
            $isNew = ($d !== '' && strpos($d, '0000') !== 0 && $d >= $newSince);
        ?>
        <div class="amc2-card" data-id="<?= $id ?>" data-name="<?= htmlspecialchars(mb_strtolower((string)$name)) ?>">
            <label class="amc2-check" title="Pilih untuk cetak massal"><input type="checkbox" class="amc2-sel" value="<?= $id ?>"><span></span></label>
            <?php if ($isNew): ?><span class="amc2-new">BARU</span><?php endif; ?>
            <div class="amc2-ava"><?php if ($photo): ?><img src="<?= htmlspecialchars($photo) ?>" alt=""><?php else: ?><?= htmlspecialchars(amc_initials($name)) ?><?php endif; ?></div>
            <span class="amc2-code"><?= htmlspecialchars((string)$code) ?></span>
            <strong class="amc2-name"><?= htmlspecialchars((string)$name) ?></strong>
            <a class="amc2-btn" target="_blank" href="index.php?mod=membership&act=awesome_member_card&action=print&member_id=<?= $id ?>">Cetak Kartu</a>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="amc2-empty">Belum ada data anggota. Silakan tambahkan melalui menu <b>Tambah Anggota</b>.</div>
    <?php endif; ?>
    </div>

    <div class="amc2-fab" id="amc2fab">
        <span id="amc2fabcount">0 kartu dipilih</span>
        <button class="f1" type="button" onclick="amc2open('cards')" title="Maksimal 3 anggota per lembar A4 dengan garis lipat">Cetak Hemat</button>
        <button class="f2" type="button" onclick="amc2open('a4front')">A4 Depan</button>
        <button class="f2" type="button" onclick="amc2open('a4back')">A4 Belakang</button>
        <button class="f2" type="button" onclick="amc2open('fold')">Lipat Presisi</button>
        <button class="f3" type="button" onclick="amc2open('cards','pdf')">Ekspor PDF</button>
        <button class="fx" type="button" onclick="amc2clear()">Batal</button>
    </div>
</div>
<script>
function amc2cards(){ return Array.prototype.slice.call(document.querySelectorAll('#amc2grid .amc2-card')); }
function amc2ids(){ return Array.prototype.slice.call(document.querySelectorAll('.amc2-sel:checked')).map(function(c){ return c.value; }); }
function amc2sync(){
    var n = amc2ids().length;
    var fab = document.getElementById('amc2fab');
    if (fab) { fab.classList.toggle('show', n > 0); document.getElementById('amc2fabcount').textContent = n + ' kartu dipilih'; }
    amc2cards().forEach(function(c){ c.classList.toggle('sel', c.querySelector('.amc2-sel').checked); });
}
function amc2clear(){ document.querySelectorAll('.amc2-sel').forEach(function(c){ c.checked = false; }); amc2sync(); }
function amc2all(){
    var vis = amc2cards().filter(function(c){ return c.style.display !== 'none'; });
    var all = vis.length && vis.every(function(c){ return c.querySelector('.amc2-sel').checked; });
    vis.forEach(function(c){ c.querySelector('.amc2-sel').checked = !all; });
    amc2sync();
}
function amc2open(mode, target){
    var ids = amc2ids();
    if (!ids.length) return;
    var url = 'index.php?mod=membership&act=awesome_member_card&action=' + (target === 'pdf' ? 'batch_pdf' : 'batch_print') + '&mode=' + mode + '&ids=' + ids.join(',');
    window.open(url, '_blank');
}
function amc2sort(v){
    var grid = document.getElementById('amc2grid');
    var cards = amc2cards();
    cards.sort(function(a, b){
        if (v === 'name') return (a.dataset.name || '').localeCompare(b.dataset.name || '');
        return (+b.dataset.id) - (+a.dataset.id);
    });
    cards.forEach(function(c){ grid.appendChild(c); });
}
function amc2filter(q){
    q = q.toLowerCase();
    var visible = 0;
    amc2cards().forEach(function(c){
        var show = c.textContent.toLowerCase().indexOf(q) !== -1;
        c.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    var el = document.getElementById('amc2count');
    if (el) el.textContent = 'Menampilkan ' + visible + ' dari ' + amc2cards().length + ' kartu anggota (anggota terbaru tampil lebih dahulu).';
}
document.addEventListener('change', function(e){ if (e.target && e.target.classList.contains('amc2-sel')) amc2sync(); });
document.addEventListener('DOMContentLoaded', function(){ amc2filter(''); amc2sync(); });
</script>