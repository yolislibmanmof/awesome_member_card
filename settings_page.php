<?php
global $dbs;
require_once __DIR__ . '/amc_lib.php';
require_once __DIR__ . '/migration.php';
run_awesome_card_migration();
$S = amc_settings();
$saved = isset($_GET['saved']);
$err   = htmlspecialchars((string)($_GET['err'] ?? ''));
$logo  = amc_logo_data($S);
$sign  = amc_sign_data($S);
$saveUrl = 'index.php?mod=membership&act=awesome_member_card&action=settings_save';
$font  = ['modern'=>"'Segoe UI',sans-serif",'serif'=>"Georgia,'Times New Roman',serif",'mono'=>"'Courier New',monospace"][$S['card_font'] ?? 'modern'] ?? "'Segoe UI',sans-serif";
$signTitle = trim((string)($S['sign_title'] ?? 'Kepala Perpustakaan'));
$signName  = trim((string)($S['sign_name'] ?? ''));
$frontSign = ($signName !== '' ? $signName . ' — ' : '') . $signTitle;
?>
<script>
(function(){
    var chrome = document.querySelector('nav, aside, header, .sidebar, #sidebar, .menuBox');
    if (!chrome && window.top === window.self) { window.location.replace('index.php?mod=membership'); }
})();
</script>
<style>
.amc4,.amc4 *,.amc4 *::before,.amc4 *::after{box-sizing:border-box;}
.amc4{font-family:'Segoe UI',sans-serif;margin:10px 0 30px;display:grid;grid-template-columns:1fr 400px;gap:22px;align-items:start;}
@media(max-width:1100px){.amc4{grid-template-columns:1fr;}}
.amc4 h2{grid-column:1/-1;font-size:22px;margin:0;color:#0f172a;}
.amc4-msg{grid-column:1/-1;background:#dcfce7;color:#166534;border:1px solid #86efac;padding:10px 14px;border-radius:10px;font-size:13px;}
.amc4-err{grid-column:1/-1;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;padding:10px 14px;border-radius:10px;font-size:13px;}
.amc4-panel{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:22px;box-shadow:0 4px 18px rgba(15,23,42,.06);}
.amc4-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.amc4-grid label{display:block;font-size:11px;font-weight:700;color:#475569;margin-bottom:5px;text-transform:uppercase;letter-spacing:.6px;}
.amc4-grid input[type=text],.amc4-grid input[type=number],.amc4-grid select,.amc4-grid textarea{width:100%;padding:9px 11px;border:1px solid #cbd5e1;border-radius:9px;font-size:13px;outline:none;font-family:inherit;}
.amc4-grid input:focus,.amc4-grid select:focus,.amc4-grid textarea:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15);}
.amc4-grid input[type=color]{width:100%;height:38px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;padding:3px;cursor:pointer;}
.amc4-full{grid-column:1/-1;}
.amc4-checks{grid-column:1/-1;display:flex;gap:16px;flex-wrap:wrap;}
.amc4-checks label{display:flex;align-items:center;gap:7px;font-size:13px;color:#334155;text-transform:none;letter-spacing:0;margin:0;cursor:pointer;}
.amc4-checks input{width:16px;height:16px;accent-color:#3b82f6;}
.amc4-save{margin-top:18px;width:100%;background:linear-gradient(90deg,#1e3a8a,#3b82f6);color:#fff;border:none;padding:12px;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;}
.amc4-save:hover{filter:brightness(1.12);}
.amc4-save:disabled{opacity:.6;cursor:wait;}
.amc4-side{position:sticky;top:10px;}
.amc4-side h3{font-size:13px;text-transform:uppercase;letter-spacing:1px;color:#64748b;margin:0 0 10px;}
.pv-tabs{display:flex;gap:8px;margin-bottom:10px;}
.pv-tabs button{flex:1;padding:8px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;color:#64748b;transition:all .2s;}
.pv-tabs button.active{background:#1e3a8a;color:#fff;border-color:#1e3a8a;}
.pv-stage{perspective:1200px;width:100%;aspect-ratio:85.6/54;}
.pv-inner{position:relative;width:100%;height:100%;transition:transform .8s;transform-style:preserve-3d;}
.pv-stage.flipped .pv-inner{transform:rotateY(180deg);}
.pv-face{position:absolute;inset:0;backface-visibility:hidden;border-radius:14px;overflow:hidden;box-shadow:0 18px 40px rgba(15,23,42,.35);}
.pv-face.back{transform:rotateY(180deg);}
.pv{--p:<?= $S['primary_color'] ?>;--s:<?= $S['secondary_color'] ?>;--a:<?= $S['accent_color'] ?>;position:relative;width:100%;height:100%;color:#fff;padding:13px 15px;display:flex;flex-direction:column;justify-content:space-between;gap:6px;font-family:<?= $font ?>;}
.pv.st-aurora{background:linear-gradient(135deg,var(--p),var(--s) 70%,var(--a) 130%);}
.pv.st-glass{background:linear-gradient(160deg,rgba(255,255,255,.32),rgba(255,255,255,.06)),linear-gradient(135deg,var(--p),var(--s));border:1px solid rgba(255,255,255,.55);}
.pv.st-dark{background:linear-gradient(135deg,#0b1220,#16233f 60%,#0b1220);}
.pv .fx{position:absolute;inset:0;background:radial-gradient(circle at 85% 12%,var(--a) 0,transparent 42%),radial-gradient(circle at 10% 90%,#fff 0,transparent 38%);opacity:.45;filter:blur(10px);}
.pv .rings{position:absolute;inset:0;background:repeating-radial-gradient(circle at 88% 85%,rgba(255,255,255,.16) 0 1px,transparent 1px 14px);opacity:.6;}
.pv .shine{position:absolute;inset:0;background:linear-gradient(105deg,transparent 42%,rgba(255,255,255,.3) 50%,transparent 58%);animation:pvsweep 3.6s linear infinite;}
@keyframes pvsweep{0%{transform:translateX(-70%)}100%{transform:translateX(70%)}}
.pv .r{position:relative;z-index:2;display:flex;justify-content:space-between;align-items:center;gap:8px;min-width:0;}
.pv .brand{display:flex;align-items:center;gap:7px;min-width:0;}
.pv .lg{width:24px;height:24px;flex:none;border-radius:6px;background:#fff;color:var(--p);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:10px;overflow:hidden;}
.pv .lg img{width:100%;height:100%;object-fit:contain;}
.pv .brand b{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;white-space:normal;overflow:hidden;font-size:10px;letter-spacing:.6px;text-transform:uppercase;line-height:1.15;}
.pv .brand span{display:block;font-size:7px;opacity:.85;letter-spacing:1px;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pv .chip{width:22px;height:15px;flex:none;border-radius:4px;background:linear-gradient(135deg,var(--a),#f59e0b);}
.pv .mid{display:flex;gap:10px;align-items:center;flex:1 1 0;min-height:0;overflow:hidden;}
.pv .photo{width:46px;height:56px;flex:none;border-radius:7px;border:2px solid rgba(255,255,255,.75);background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;overflow:hidden;}
.pv .photo img{width:100%;height:100%;object-fit:cover;}
.pv .photo .ini{font-size:17px;font-weight:800;}
.pv .who{min-width:0;flex:1;overflow:hidden;}
.pv .who h1{margin:0;font-size:13px;letter-spacing:1px;text-transform:uppercase;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pv .who .cd{font-family:'Courier New',monospace;font-size:9px;letter-spacing:2.5px;opacity:.95;margin:2px 0 4px;}
.pv .flds{display:grid;grid-template-columns:1fr 1fr;gap:2px 8px;font-size:7px;}
.pv .flds label{opacity:.75;text-transform:uppercase;font-size:6px;letter-spacing:.5px;display:block;}
.pv .bot{align-items:flex-end;gap:8px;flex:none;}
.pv .sign{font-size:7px;opacity:.9;min-width:0;}
.pv .sign img{height:22px;display:block;margin-bottom:1px;}
.pv .sign .line{width:72px;border-bottom:1px solid rgba(255,255,255,.8);margin-bottom:2px;height:5px;}
.pv .qr{background:#fff;border-radius:5px;padding:3px 3px 1px;flex:none;display:flex;flex-direction:column;align-items:center;gap:1px;}
.pv .qr svg{width:42px;height:42px;}
.pv .qr .num{font-family:'Courier New',monospace;font-size:6px;letter-spacing:1px;color:#0f172a;}
.pv .foot{position:relative;z-index:2;font-size:6.5px;opacity:.7;letter-spacing:.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:none;}
.pv .stripe{position:relative;z-index:2;height:22px;margin:-13px -15px 0;flex:none;background:linear-gradient(90deg,#0b1220,#26364f 45%,#0b1220);border-bottom:2px solid var(--a);}
.pv .sigpanel{background:#fff;color:#0f172a;border-radius:6px;padding:3px 8px 2px;width:145px;flex:none;text-align:center;}
.pv .sigpanel .space{height:20px;}
.pv .sigpanel img{height:26px;display:block;margin:0 auto;}
.pv .sigpanel b{display:block;font-size:7px;margin-top:1px;}
.pv .sigpanel span{font-size:6px;text-transform:uppercase;letter-spacing:.6px;color:#64748b;display:block;margin-top:1px;}
.pv .validbox{text-align:right;}
.pv .validbox .vl{display:inline-block;background:var(--a);color:#1e293b;font-size:6.5px;font-weight:800;padding:2px 7px;border-radius:5px;letter-spacing:1px;}
.pv .validbox b{display:block;font-size:14px;letter-spacing:2px;margin-top:2px;}
.pv .validbox i{display:block;font-style:normal;font-size:6.5px;opacity:.85;margin-top:1px;white-space:nowrap;}
.pv .terms{position:relative;z-index:2;flex:1 1 0;min-height:0;overflow:hidden;}
.pv .terms label{display:block;font-size:6.5px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--a);margin-bottom:2px;}
.pv .terms ol{margin:0;padding-left:11px;}
.pv .terms li{font-size:6.8px;line-height:1.25;opacity:.94;margin-bottom:1px;}
.pv .backfoot{align-items:flex-end;gap:8px;flex:none;}
.pv .backfoot .brand b{font-size:8px;}
.pv .backfoot .brand span{font-size:6px;}
.pv .backfoot .lg{width:18px;height:18px;font-size:8px;}
.pv .backfoot .cd{font-family:'Courier New',monospace;font-size:7px;letter-spacing:2px;opacity:.9;}
</style>

<div class="amc4">
    <h2>Pengaturan Kartu</h2>
    <?php if ($saved): ?><div class="amc4-msg">Pengaturan berhasil disimpan dan langsung diterapkan pada kartu.</div><?php endif; ?>
    <?php if ($err !== ''): ?><div class="amc4-err">Catatan: <?= $err ?></div><?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($saveUrl) ?>" enctype="multipart/form-data" class="amc4-panel">
        <div class="amc4-grid">
            <div><label>Nama Perpustakaan</label><input type="text" id="f_lib" name="library_name" value="<?= htmlspecialchars($S['library_name']) ?>"></div>
            <div><label>Subjudul Kartu</label><input type="text" id="f_sub" name="card_subtitle" value="<?= htmlspecialchars($S['card_subtitle']) ?>"></div>
            <div><label>Warna Utama</label><input type="color" id="f_p" name="primary_color" value="<?= htmlspecialchars($S['primary_color']) ?>"></div>
            <div><label>Warna Kedua</label><input type="color" id="f_s" name="secondary_color" value="<?= htmlspecialchars($S['secondary_color']) ?>"></div>
            <div><label>Warna Aksen</label><input type="color" id="f_a" name="accent_color" value="<?= htmlspecialchars($S['accent_color']) ?>"></div>
            <div><label>Gaya Kartu</label>
                <select id="f_st" name="card_style">
                    <option value="aurora" <?= $S['card_style']==='aurora'?'selected':'' ?>>Aurora</option>
                    <option value="glass"  <?= $S['card_style']==='glass'?'selected':'' ?>>Glass</option>
                    <option value="dark"   <?= $S['card_style']==='dark'?'selected':'' ?>>Dark Carbon</option>
                </select>
            </div>
            <div><label>Jenis Huruf Kartu</label>
                <select id="f_fo" name="card_font">
                    <option value="modern" <?= $S['card_font']==='modern'?'selected':'' ?>>Modern (Sans)</option>
                    <option value="serif"  <?= $S['card_font']==='serif'?'selected':'' ?>>Klasik (Serif)</option>
                    <option value="mono"   <?= $S['card_font']==='mono'?'selected':'' ?>>Monospace</option>
                </select>
            </div>
            <div><label>Masa Berlaku (tahun)</label><input type="number" id="f_vy" name="valid_years" min="1" max="10" value="<?= (int)$S['valid_years'] ?>"></div>
            <div><label>Jabatan Penanda Tangan</label><input type="text" id="f_stt" name="sign_title" value="<?= htmlspecialchars($S['sign_title']) ?>"></div>
            <div><label>Nama Penanda Tangan (opsional)</label><input type="text" id="f_snm" name="sign_name" value="<?= htmlspecialchars($S['sign_name']) ?>"></div>
            <div><label>Logo (PNG/JPG maks 1MB)</label><input type="file" id="f_lg" name="logo" accept="image/*"></div>
            <div><label>Tanda Tangan (PNG transparan disarankan)</label><input type="file" id="f_sn" name="sign" accept="image/*"></div>
            <div class="amc4-full"><label>Teks Kaki Kartu (depan)</label><input type="text" id="f_ft" name="footer_text" value="<?= htmlspecialchars($S['footer_text']) ?>"></div>
            <div class="amc4-full"><label>Ketentuan Penggunaan (satu baris per poin, tampil di belakang)</label>
                <textarea id="f_tm" name="card_terms" rows="5"><?= htmlspecialchars($S['card_terms']) ?></textarea>
            </div>
            <div class="amc4-checks">
                <label><input type="checkbox" id="f_ph" name="show_photo" <?= $S['show_photo']==='1'?'checked':'' ?>> Foto/Inisial</label>
                <label><input type="checkbox" id="f_qr" name="show_qr" <?= $S['show_qr']==='1'?'checked':'' ?>> QR Code</label>
                <label><input type="checkbox" id="f_sg" name="show_signature" <?= $S['show_signature']==='1'?'checked':'' ?>> Tanda Tangan (depan)</label>
                <label><input type="checkbox" id="f_hl" name="show_holo" <?= $S['show_holo']==='1'?'checked':'' ?>> Hologram</label>
                <label><input type="checkbox" id="f_cp" name="show_chip" <?= $S['show_chip']==='1'?'checked':'' ?>> Chip</label>
                <?php if ($S['logo_file'] !== ''): ?><label><input type="checkbox" name="logo_remove"> Hapus logo</label><?php endif; ?>
                <?php if ($S['sign_file'] !== ''): ?><label><input type="checkbox" name="sign_remove"> Hapus tanda tangan</label><?php endif; ?>
            </div>
        </div>
        <button class="amc4-save" type="submit">Simpan & Terapkan</button>
    </form>

    <div class="amc4-side">
        <h3>Pratinjau Langsung</h3>
        <div class="pv-tabs">
            <button type="button" id="tab_front" class="active">Sisi Depan</button>
            <button type="button" id="tab_back">Sisi Belakang</button>
        </div>
        <div class="pv-stage" id="pv_stage">
            <div class="pv-inner">
                <div class="pv-face front">
                    <div class="pv st-<?= htmlspecialchars($S['card_style']) ?>" id="pv_front">
                        <div class="fx"></div><div class="rings"></div><div class="shine"></div>
                        <div class="r">
                            <div class="brand">
                                <div class="lg" id="pv_lg"><?php if ($logo): ?><img src="<?= $logo ?>" alt=""><?php else: ?><span id="pv_lgi"><?= htmlspecialchars(amc_initials($S['library_name'])) ?></span><?php endif; ?></div>
                                <div><b id="pv_lib"><?= htmlspecialchars($S['library_name']) ?></b><span id="pv_sub"><?= htmlspecialchars($S['card_subtitle']) ?></span></div>
                            </div>
                            <span class="chip" id="pv_chip"></span>
                        </div>
                        <div class="r mid">
                            <div class="photo" id="pv_ph"><span class="ini">YL</span></div>
                            <div class="who">
                                <h1>Nama Anggota</h1>
                                <div class="cd">1 2 3 4 5 6 7 8 9</div>
                                <div class="flds">
                                    <div><label>ID Anggota</label>123</div>
                                    <div><label>Telepon</label>0812-0000</div>
                                    <div><label>Email</label>anggota@mail</div>
                                    <div><label>Terdaftar</label><?= date('Y-m-d') ?></div>
                                    <div style="grid-column:1/-1"><label>Alamat</label>Jl. Contoh No. 1, Kota</div>
                                </div>
                            </div>
                        </div>
                        <div class="r bot">
                            <div class="sign" id="pv_sg"><img id="pv_sgimg" src="<?= $sign ?: '' ?>" style="display:<?= $sign ? 'block' : 'none' ?>" alt=""><div class="line"></div><span id="pv_sgtext"><?= htmlspecialchars($frontSign) ?></span></div>
                            <div class="qr" id="pv_qr"><?= amc_qr_svg('123456789', '42px') ?><div class="num">1 2 3 4 5 6 7 8 9</div></div>
                        </div>
                        <div class="foot" id="pv_ft"><?= htmlspecialchars($S['footer_text']) ?></div>
                    </div>
                </div>
                <div class="pv-face back">
                    <div class="pv st-<?= htmlspecialchars($S['card_style']) ?>" id="pv_back">
                        <div class="fx"></div><div class="rings"></div>
                        <div class="stripe"></div>
                        <div class="r" style="margin-top:2px;">
                            <div class="sigpanel"><img id="pv_sigimg" src="<?= $sign ?: '' ?>" style="display:<?= $sign ? 'block' : 'none' ?>" alt=""><div class="space" id="pv_sigspace" style="display:<?= $sign ? 'none' : 'block' ?>"></div><b id="pv_signame" style="display:<?= $signName !== '' ? 'block' : 'none' ?>"><?= htmlspecialchars($signName) ?></b><span id="pv_sigtitle">Tanda Tangan <?= htmlspecialchars($signTitle) ?></span></div>
                            <div class="validbox">
                                <span class="vl">BERLAKU S/D</span>
                                <b id="pv_vy_b"></b>
                                <i>Diterbitkan: <?= date('d/m/Y') ?></i>
                            </div>
                        </div>
                        <div class="terms">
                            <label>Ketentuan Penggunaan</label>
                            <ol id="pv_terms"></ol>
                        </div>
                        <div class="r backfoot">
                            <div class="brand">
                                <div class="lg" id="pv_lg2"><?php if ($logo): ?><img src="<?= $logo ?>" alt=""><?php else: ?><span id="pv_lgi2"><?= htmlspecialchars(amc_initials($S['library_name'])) ?></span><?php endif; ?></div>
                                <div><b id="pv_lib2"><?= htmlspecialchars($S['library_name']) ?></b><span id="pv_ft2"><?= htmlspecialchars($S['footer_text']) ?></span></div>
                            </div>
                            <span class="cd">1 2 3 4 5 6 7 8 9</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    var $ = function(id){ return document.getElementById(id); };
    var stage = $('pv_stage');
    var fontMap = {modern:"'Segoe UI',sans-serif", serif:"Georgia,'Times New Roman',serif", mono:"'Courier New',monospace"};

    $('tab_front').addEventListener('click', function(){ stage.classList.remove('flipped'); this.classList.add('active'); $('tab_back').classList.remove('active'); });
    $('tab_back').addEventListener('click', function(){ stage.classList.add('flipped'); this.classList.add('active'); $('tab_front').classList.remove('active'); });

    function validDate(y){ var d = new Date(); d.setFullYear(d.getFullYear() + (parseInt(y,10)||1));
        return ('0'+d.getDate()).slice(-2)+'/'+('0'+(d.getMonth()+1)).slice(-2)+'/'+d.getFullYear(); }
    function renderTerms(text){
        var ol = $('pv_terms'); if (!ol) return;
        ol.innerHTML = '';
        String(text).split(/\r?\n/).filter(function(l){ return l.trim() !== ''; }).slice(0,6).forEach(function(line){
            var li = document.createElement('li'); li.textContent = line; ol.appendChild(li);
        });
    }
    function applyStyle(el, p, s, a, style, fm){
        if (!el) return;
        el.style.setProperty('--p', p); el.style.setProperty('--s', s); el.style.setProperty('--a', a);
        el.style.fontFamily = fm; el.className = 'pv st-' + style;
    }
    function setText(id, val){ var el = $(id); if (el) el.textContent = val; }
    function setDisp(id, show){ var el = $(id); if (el) el.style.display = show ? '' : 'none'; }

    function refresh(){
        var p = $('f_p').value, s = $('f_s').value, a = $('f_a').value, st = $('f_st').value;
        var fm = fontMap[$('f_fo').value] || fontMap.modern;
        var lib = $('f_lib').value || 'Perpustakaan';
        var title = $('f_stt').value || 'Kepala Perpustakaan';
        var sname = $('f_snm').value || '';
        applyStyle($('pv_front'), p, s, a, st, fm);
        applyStyle($('pv_back'),  p, s, a, st, fm);
        setText('pv_lib', lib);  setText('pv_lib2', lib);
        setText('pv_sub', $('f_sub').value || '');
        setText('pv_ft', $('f_ft').value || ''); setText('pv_ft2', $('f_ft').value || '');
        setText('pv_vy_b', validDate($('f_vy').value));
        setText('pv_sgtext', (sname ? sname + ' — ' : '') + title);
        setText('pv_sigtitle', 'Tanda Tangan ' + title);
        setText('pv_signame', sname);
        var sn = $('pv_signame'); if (sn) sn.style.display = sname ? 'block' : 'none';
        setDisp('pv_ph', $('f_ph').checked);
        setDisp('pv_qr', $('f_qr').checked);
        setDisp('pv_sg', $('f_sg').checked);
        setDisp('pv_chip', $('f_cp').checked);
        var ini = (lib.trim().split(/\s+/).map(function(w){ return w.charAt(0); }).join('').substring(0,2)).toUpperCase();
        setText('pv_lgi', ini); setText('pv_lgi2', ini);
        renderTerms($('f_tm').value || '');
    }
    ['f_lib','f_sub','f_p','f_s','f_a','f_st','f_fo','f_vy','f_ft','f_tm','f_stt','f_snm','f_ph','f_qr','f_sg','f_hl','f_cp'].forEach(function(id){
        var el = $(id); if (el) el.addEventListener('input', refresh);
    });
    function bindImage(inputId, targets){
        var inp = $(inputId); if (!inp) return;
        inp.addEventListener('change', function(){
            var f = this.files[0]; if (!f) return;
            var r = new FileReader();
            r.onload = function(e){
                targets.forEach(function(t){
                    var el = $(t.id);
                    if (!el) return;
                    if (t.tag === 'img') { el.src = e.target.result; el.style.display = 'block'; }
                    else { el.innerHTML = '<img src="' + e.target.result + '" alt="">'; }
                });
                var sp = $('pv_sigspace'); if (sp && inputId === 'f_sn') sp.style.display = 'none';
            };
            r.readAsDataURL(f);
        });
    }
    bindImage('f_lg', [{id:'pv_lg',tag:'box'},{id:'pv_lg2',tag:'box'}]);
    bindImage('f_sn', [{id:'pv_sgimg',tag:'img'},{id:'pv_sigimg',tag:'img'}]);

    function showMsg(txt, ok){
        var old = document.getElementById('amc4_live_msg'); if (old) old.remove();
        var d = document.createElement('div');
        d.id = 'amc4_live_msg'; d.className = ok ? 'amc4-msg' : 'amc4-err'; d.textContent = txt;
        var h2 = document.querySelector('.amc4 h2');
        if (h2 && h2.parentNode) h2.parentNode.insertBefore(d, h2.nextSibling);
        setTimeout(function(){ d.remove(); }, 6000);
    }
    var form = document.querySelector('form.amc4-panel');
    if (form) form.addEventListener('submit', function(e){
        e.preventDefault();
        var btn = form.querySelector('.amc4-save');
        btn.disabled = true; btn.textContent = 'Menyimpan...';
        fetch(form.action + '&ajax=1', {method:'POST', body:new FormData(form), headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){ return r.json(); }).then(function(j){
            btn.disabled = false; btn.textContent = 'Simpan & Terapkan';
            showMsg(j.err ? ('Catatan: ' + j.err) : 'Pengaturan berhasil disimpan dan langsung diterapkan pada kartu.', !j.err);
        }).catch(function(){ btn.disabled = false; btn.textContent = 'Simpan & Terapkan'; form.submit(); });
    });
    refresh();
})();
</script>