<?php
/**
 * Awesome Member Card — Pengaturan desain kartu (final 1.6.0)
 * Perbaikan: pratinjau hologram, rasio kartu sesuai ukuran aktif, inisial netral,
 *            validasi ukuran berkas di sisi klien, anti simpan-ganda pada AJAX,
 *            maxlength teks, pratinjau hapus logo/ttd, guard redirect.
 */
defined('INDEX_AUTH') OR die('Direct access not allowed!');
global $dbs;
require_once __DIR__ . '/amc_lib.php';

$S = amc_settings();

if (session_status() !== PHP_SESSION_ACTIVE) {
die('Sesi admin belum siap. Buka halaman ini melalui menu admin SLiMS.');
}
if (empty($_SESSION['amc_csrf_token'])) { $_SESSION['amc_csrf_token'] = bin2hex(random_bytes(32)); }
$csrf_token = (string)$_SESSION['amc_csrf_token'];

$saved   = isset($_GET['saved']);
$err     = htmlspecialchars((string)($_GET['err'] ?? ''));
$logo    = amc_logo_data($S);
$sign    = amc_sign_data($S);
$saveUrl = 'index.php?mod=membership&act=awesome_member_card&action=settings_save';

/* Ukuran kartu AKTIF (selaras dengan inc_card_render.php) */
$cw = defined('AMC_CW') ? (float)AMC_CW : 91;
$ch = defined('AMC_CH') ? (float)AMC_CH : 62;

$css_p = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($S['primary_color'] ?? '')) ? $S['primary_color'] : '#1e3a8a';
$css_s = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($S['secondary_color'] ?? '')) ? $S['secondary_color'] : '#3b82f6';
$css_a = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($S['accent_color'] ?? '')) ? $S['accent_color'] : '#fbbf24';

$fontMap = ['modern' => "'Segoe UI',sans-serif", 'serif' => "Georgia,'Times New Roman',serif", 'mono' => "'Courier New',monospace"];
$font    = $fontMap[$S['card_font'] ?? 'modern'] ?? $fontMap['modern'];

$signTitle = trim((string)($S['sign_title'] ?? 'Kepala Perpustakaan'));
$signName  = trim((string)($S['sign_name'] ?? ''));
$frontSign = ($signName !== '' ? $signName . ' — ' : '') . $signTitle;

$lib_name        = htmlspecialchars($S['library_name'] ?? 'Perpustakaan Digital');
$card_sub        = htmlspecialchars($S['card_subtitle'] ?? 'Library Member Card');
$card_style      = in_array($S['card_style'] ?? 'aurora', ['aurora','glass','dark'], true) ? $S['card_style'] : 'aurora';
$card_font       = in_array($S['card_font'] ?? 'modern', ['modern','serif','mono'], true) ? $S['card_font'] : 'modern';
$valid_years     = (int)($S['valid_years'] ?? 2);
$sign_title_esc  = htmlspecialchars($signTitle);
$sign_name_esc   = htmlspecialchars($signName);
$footer_text_esc = htmlspecialchars($S['footer_text'] ?? '');
$card_terms_esc  = htmlspecialchars($S['card_terms'] ?? '');

$show_photo = ($S['show_photo'] ?? '1') === '1';
$show_qr    = ($S['show_qr'] ?? '1') === '1';
$show_sig   = ($S['show_signature'] ?? '1') === '1';
$show_holo  = ($S['show_holo'] ?? '1') === '1';
$show_chip  = ($S['show_chip'] ?? '1') === '1';
$has_logo   = ($S['logo_file'] ?? '') !== '';
$has_sign   = ($S['sign_file'] ?? '') !== '';
$pvInitials = amc_initials('Nama Anggota');
?>
<script>
(function(){
    /* Guard anti-loop: hanya alihkan bila benar-benar tanpa wrapper admin */
    try {
        if (sessionStorage.getItem('amc4_redirect') === '1') return;
        var chrome = document.querySelector('nav, aside, header, .sidebar, #sidebar, .menuBox, .mainMenu, #mainMenu');
        if (!chrome && window.top === window.self && !document.querySelector('.amc4')) {
            sessionStorage.setItem('amc4_redirect', '1');
            window.location.replace('index.php?mod=membership');
        } else { sessionStorage.removeItem('amc4_redirect'); }
    } catch (e) {}
})();
</script>
<style>
.amc4,.amc4 *,.amc4 *::before,.amc4 *::after{box-sizing:border-box;}
.amc4{font-family:'Segoe UI',sans-serif;margin:10px 0 30px;display:grid;grid-template-columns:1fr 400px;gap:22px;align-items:start;}
@media(max-width:1100px){.amc4{grid-template-columns:1fr;}}
.amc4 h2{grid-column:1/-1;font-size:22px;margin:0;color:#0f172a;}
.amc4-msg{grid-column:1/-1;background:#dcfce7;color:#166534;border:1px solid #86efac;padding:10px 14px;border-radius:10px;font-size:13px;}
.amc4-err{grid-column:1/-1;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;padding:10px 14px;border-radius:10px;font-size:13px;line-height:1.6;}
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
.amc4-info{grid-column:1/-1;background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;border-radius:10px;padding:10px 13px;font-size:12px;line-height:1.6;}
.amc4-side{position:sticky;top:10px;}
.amc4-side h3{font-size:13px;text-transform:uppercase;letter-spacing:1px;color:#64748b;margin:0 0 10px;}
.pv-tabs{display:flex;gap:8px;margin-bottom:10px;}
.pv-tabs button{flex:1;padding:8px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;color:#64748b;transition:all .2s;}
.pv-tabs button.active{background:#1e3a8a;color:#fff;border-color:#1e3a8a;}
.pv-stage{perspective:1200px;width:100%;aspect-ratio:<?= $cw ?>/<?= $ch ?>;}
.pv-inner{position:relative;width:100%;height:100%;transition:transform .8s;transform-style:preserve-3d;}
.pv-stage.flipped .pv-inner{transform:rotateY(180deg);}
.pv-face{position:absolute;inset:0;backface-visibility:hidden;border-radius:14px;overflow:hidden;box-shadow:0 18px 40px rgba(15,23,42,.35);}
.pv-face.back{transform:rotateY(180deg);}
.pv{--p:<?= $css_p ?>;--s:<?= $css_s ?>;--a:<?= $css_a ?>;position:relative;width:100%;height:100%;color:#fff;padding:13px 15px;display:flex;flex-direction:column;justify-content:space-between;gap:6px;font-family:<?= $font ?>;}
.pv.st-aurora{background:linear-gradient(135deg,var(--p),var(--s) 70%,var(--a) 130%);}
.pv.st-glass{background:linear-gradient(160deg,rgba(255,255,255,.32),rgba(255,255,255,.06)),linear-gradient(135deg,var(--p),var(--s));border:1px solid rgba(255,255,255,.55);}
.pv.st-dark{background:linear-gradient(135deg,#0b1220,#16233f 60%,#0b1220);}
.pv .fx{position:absolute;inset:0;background:radial-gradient(circle at 85% 12%,var(--a) 0,transparent 42%),radial-gradient(circle at 10% 90%,#fff 0,transparent 38%);opacity:.45;filter:blur(10px);}
.pv .rings{position:absolute;inset:0;background:repeating-radial-gradient(circle at 88% 85%,rgba(255,255,255,.16) 0 1px,transparent 1px 14px);opacity:.6;}
.pv .shine{position:absolute;inset:0;background:linear-gradient(105deg,transparent 42%,rgba(255,255,255,.3) 50%,transparent 58%);animation:pvsweep 3.6s linear infinite;}
.pv .holo{position:absolute;width:52px;height:52px;border-radius:50%;right:118px;bottom:48px;background:conic-gradient(#ff004c,#ff9a00,#00ffa3,#00c8ff,#a000ff,#ff004c);opacity:.28;mix-blend-mode:screen;filter:blur(2px);}
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
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="amc4-grid">
            <div class="amc4-info">Ukuran kartu aktif: <b><?= $cw ?> &times; <?= $ch ?> mm</b>. Pratinjau di samping memakai rasio yang sama. Logo &amp; tanda tangan: PNG/JPG/GIF/WEBP maksimal <b>1 MB</b>.</div>
            <div><label>Nama Perpustakaan</label><input type="text" id="f_lib" name="library_name" maxlength="80" value="<?= $lib_name ?>"></div>
            <div><label>Subjudul Kartu</label><input type="text" id="f_sub" name="card_subtitle" maxlength="60" value="<?= $card_sub ?>"></div>
            <div><label>Warna Utama</label><input type="color" id="f_p" name="primary_color" value="<?= htmlspecialchars($css_p) ?>"></div>
            <div><label>Warna Kedua</label><input type="color" id="f_s" name="secondary_color" value="<?= htmlspecialchars($css_s) ?>"></div>
            <div><label>Warna Aksen</label><input type="color" id="f_a" name="accent_color" value="<?= htmlspecialchars($css_a) ?>"></div>
            <div><label>Gaya Kartu</label>
                <select id="f_st" name="card_style">
                    <option value="aurora" <?= $card_style === 'aurora' ? 'selected' : '' ?>>Aurora</option>
                    <option value="glass"  <?= $card_style === 'glass' ? 'selected' : '' ?>>Glass</option>
                    <option value="dark"   <?= $card_style === 'dark' ? 'selected' : '' ?>>Dark Carbon</option>
                </select>
            </div>
            <div><label>Jenis Huruf Kartu</label>
                <select id="f_fo" name="card_font">
                    <option value="modern" <?= $card_font === 'modern' ? 'selected' : '' ?>>Modern (Sans)</option>
                    <option value="serif"  <?= $card_font === 'serif' ? 'selected' : '' ?>>Klasik (Serif)</option>
                    <option value="mono"   <?= $card_font === 'mono' ? 'selected' : '' ?>>Monospace</option>
                </select>
            </div>
            <div><label>Masa Berlaku (tahun)</label><input type="number" id="f_vy" name="valid_years" min="1" max="10" value="<?= $valid_years ?>"></div>
            <div><label>Jabatan Penanda Tangan</label><input type="text" id="f_stt" name="sign_title" maxlength="60" value="<?= $sign_title_esc ?>"></div>
            <div><label>Nama Penanda Tangan (opsional)</label><input type="text" id="f_snm" name="sign_name" maxlength="60" value="<?= $sign_name_esc ?>"></div>
            <div><label>Logo (maks 1MB)</label><input type="file" id="f_lg" name="logo" accept="image/png,image/jpeg,image/gif,image/webp"></div>
            <div><label>Tanda Tangan (PNG transparan disarankan)</label><input type="file" id="f_sn" name="sign" accept="image/png,image/jpeg,image/gif,image/webp"></div>
            <div class="amc4-full"><label>Teks Kaki Kartu (depan)</label><input type="text" id="f_ft" name="footer_text" maxlength="120" value="<?= $footer_text_esc ?>"></div>
            <div class="amc4-full"><label>Ketentuan Penggunaan (satu baris per poin, maksimal 6 poin tampil)</label>
                <textarea id="f_tm" name="card_terms" rows="5" maxlength="900"><?= $card_terms_esc ?></textarea>
            </div>
            <div class="amc4-checks">
                <label><input type="checkbox" id="f_ph" name="show_photo" <?= $show_photo ? 'checked' : '' ?>> Foto/Inisial</label>
                <label><input type="checkbox" id="f_qr" name="show_qr" <?= $show_qr ? 'checked' : '' ?>> QR Code</label>
                <label><input type="checkbox" id="f_sg" name="show_signature" <?= $show_sig ? 'checked' : '' ?>> Tanda Tangan (depan)</label>
                <label><input type="checkbox" id="f_hl" name="show_holo" <?= $show_holo ? 'checked' : '' ?>> Hologram</label>
                <label><input type="checkbox" id="f_cp" name="show_chip" <?= $show_chip ? 'checked' : '' ?>> Chip</label>
                <?php if ($has_logo): ?><label><input type="checkbox" id="f_lgr" name="logo_remove"> Hapus logo</label><?php endif; ?>
                <?php if ($has_sign): ?><label><input type="checkbox" id="f_snr" name="sign_remove"> Hapus tanda tangan</label><?php endif; ?>
            </div>
        </div>
        <button class="amc4-save" type="submit">Simpan &amp; Terapkan</button>
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
                    <div class="pv st-<?= htmlspecialchars($card_style) ?>" id="pv_front">
                        <div class="fx"></div><div class="rings"></div><div class="shine"></div>
                        <div class="holo" id="pv_hl"></div>
                        <div class="r">
                            <div class="brand">
                                <div class="lg" id="pv_lg"><?php if ($logo): ?><img src="<?= htmlspecialchars($logo) ?>" alt=""><?php else: ?><span id="pv_lgi"><?= htmlspecialchars(amc_initials($S['library_name'] ?? '')) ?></span><?php endif; ?></div>
                                <div><b id="pv_lib"><?= $lib_name ?></b><span id="pv_sub"><?= $card_sub ?></span></div>
                            </div>
                            <span class="chip" id="pv_chip"></span>
                        </div>
                        <div class="r mid">
                            <div class="photo" id="pv_ph"><span class="ini"><?= htmlspecialchars($pvInitials) ?></span></div>
                            <div class="who">
                                <h1>Nama Anggota</h1>
                                <div class="cd">1 2 3 4 5 6 7 8 9</div>
                                <div class="flds">
                                    <div><label>ID Anggota</label>123</div>
                                    <div><label>Telepon</label>0812-0000</div>
                                    <div><label>Email</label>anggota@mail</div>
                                    <div><label>Terdaftar</label><?= date('d/m/Y') ?></div>
                                    <div style="grid-column:1/-1"><label>Alamat</label>Jl. Contoh No. 1, Kota</div>
                                </div>
                            </div>
                        </div>
                        <div class="r bot">
                            <div class="sign" id="pv_sg"><img id="pv_sgimg" src="<?= htmlspecialchars($sign ?: '') ?>" style="display:<?= $sign ? 'block' : 'none' ?>" alt=""><div class="line"></div><span id="pv_sgtext"><?= htmlspecialchars($frontSign) ?></span></div>
                            <div class="qr" id="pv_qr"><?= amc_qr_svg('123456789', '42px') ?><div class="num">1 2 3 4 5 6 7 8 9</div></div>
                        </div>
                        <div class="foot" id="pv_ft"><?= $footer_text_esc ?></div>
                    </div>
                </div>
                <div class="pv-face back">
                    <div class="pv st-<?= htmlspecialchars($card_style) ?>" id="pv_back">
                        <div class="fx"></div><div class="rings"></div>
                        <div class="holo" id="pv_hl2"></div>
                        <div class="stripe"></div>
                        <div class="r" style="margin-top:2px;">
                            <div class="sigpanel"><img id="pv_sigimg" src="<?= htmlspecialchars($sign ?: '') ?>" style="display:<?= $sign ? 'block' : 'none' ?>" alt=""><div class="space" id="pv_sigspace" style="display:<?= $sign ? 'none' : 'block' ?>"></div><b id="pv_signame" style="display:<?= $signName !== '' ? 'block' : 'none' ?>"><?= $sign_name_esc ?></b><span id="pv_sigtitle">Tanda Tangan <?= $sign_title_esc ?></span></div>
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
                                <div class="lg" id="pv_lg2"><?php if ($logo): ?><img src="<?= htmlspecialchars($logo) ?>" alt=""><?php else: ?><span id="pv_lgi2"><?= htmlspecialchars(amc_initials($S['library_name'] ?? '')) ?></span><?php endif; ?></div>
                                <div><b id="pv_lib2"><?= $lib_name ?></b><span id="pv_ft2"><?= $footer_text_esc ?></span></div>
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

    /* Tanggal aman untuk 29 Februari (clamp ke hari terakhir bulan) */
    function validDate(y){
        var n = parseInt(y, 10); if (!n || n < 1) n = 1;
        var d = new Date(), day = d.getDate(), mo = d.getMonth(), yr = d.getFullYear() + n;
        var last = new Date(yr, mo + 1, 0).getDate();
        var t = new Date(yr, mo, Math.min(day, last));
        return ('0'+t.getDate()).slice(-2)+'/'+('0'+(t.getMonth()+1)).slice(-2)+'/'+t.getFullYear();
    }
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
    function setDim(id, dim){ var el = $(id); if (el) el.style.opacity = dim ? '.25' : '1'; }

    function refresh(){
        var p = $('f_p').value, s = $('f_s').value, a = $('f_a').value, st = $('f_st').value;
        var fm = fontMap[$('f_fo').value] || fontMap.modern;
        var lib = $('f_lib').value || 'Perpustakaan';
        var title = $('f_stt').value || 'Kepala Perpustakaan';
        var sname = $('f_snm').value || '';
        applyStyle($('pv_front'), p, s, a, st, fm);
        applyStyle($('pv_back'),  p, s, a, st, fm);
        setText('pv_lib', lib); setText('pv_lib2', lib);
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
        setDisp('pv_hl', $('f_hl').checked);   /* hologram depan */
        setDisp('pv_hl2', $('f_hl').checked);  /* hologram belakang */
        var lr = $('f_lgr'), sr = $('f_snr');
        setDim('pv_lg', lr && lr.checked); setDim('pv_lg2', lr && lr.checked);
        setDim('pv_sgimg', sr && sr.checked); setDim('pv_sigimg', sr && sr.checked);
        var ini = (lib.trim().split(/\s+/).map(function(w){ return w.charAt(0); }).join('').substring(0,2)).toUpperCase();
        setText('pv_lgi', ini); setText('pv_lgi2', ini);
        renderTerms($('f_tm').value || '');
    }
    ['f_lib','f_sub','f_p','f_s','f_a','f_st','f_fo','f_vy','f_ft','f_tm','f_stt','f_snm','f_ph','f_qr','f_sg','f_hl','f_cp','f_lgr','f_snr'].forEach(function(id){
        var el = $(id); if (!el) return;
        el.addEventListener('input', refresh);
        el.addEventListener('change', refresh);
    });

    function showMsg(txt, ok){
        var old = document.getElementById('amc4_live_msg'); if (old) old.remove();
        var d = document.createElement('div');
        d.id = 'amc4_live_msg'; d.className = ok ? 'amc4-msg' : 'amc4-err'; d.textContent = txt;
        var h2 = document.querySelector('.amc4 h2');
        if (h2 && h2.parentNode) h2.parentNode.insertBefore(d, h2.nextSibling);
        setTimeout(function(){ if (d.parentNode) d.remove(); }, ok ? 6000 : 12000);
    }

    /* Validasi berkas di sisi klien: cegah jalur "unggah gagal" di server */
    function guardFile(inputId, label){
        var inp = $(inputId); if (!inp) return;
        inp.addEventListener('change', function(){
            var f = this.files && this.files[0]; if (!f) return;
            if (f.size > 1048576) {
                showMsg(label + ' berukuran ' + (f.size/1048576).toFixed(2) + ' MB (maksimal 1 MB). Kecilkan dulu — berkas tidak akan dikirim.', false);
                this.value = ''; return;
            }
            if (f.type && !/^image\/(png|jpeg|jpg|gif|webp)$/i.test(f.type)) {
                showMsg(label + ' harus PNG, JPG, GIF, atau WEBP.', false);
                this.value = ''; return;
            }
        });
    }
    guardFile('f_lg', 'Logo'); guardFile('f_sn', 'Tanda tangan');

    function bindImage(inputId, targets){
        var inp = $(inputId); if (!inp) return;
        inp.addEventListener('change', function(){
            var f = this.files && this.files[0]; if (!f) return;
            var r = new FileReader();
            r.onload = function(e){
                targets.forEach(function(t){
                    var el = $(t.id); if (!el) return;
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

    /* AJAX: TIDAK pernah submit ulang otomatis (mencegah simpan & unggah ganda) */
    var form = document.querySelector('form.amc4-panel');
    if (form) form.addEventListener('submit', function(e){
        e.preventDefault();
        var btn = form.querySelector('.amc4-save');
        btn.disabled = true; btn.textContent = 'Menyimpan...';
        fetch(form.action + '&ajax=1', { method:'POST', credentials:'same-origin', body:new FormData(form),
            headers:{'X-Requested-With':'XMLHttpRequest'} })
        .then(function(r){ return r.text().then(function(t){ return { status:r.status, text:t }; }); })
        .then(function(res){
            btn.disabled = false; btn.textContent = 'Simpan & Terapkan';
            var j = null; try { j = JSON.parse(res.text); } catch (err) {}
            if (!j) {
                showMsg('Respons server tidak valid (HTTP ' + res.status + '). Pengaturan MUNGKIN sudah tersimpan — muat ulang halaman untuk memeriksa. Jangan kirim ulang berkas.', false);
                return;
            }
            showMsg(j.err ? ('Catatan: ' + j.err) : 'Pengaturan berhasil disimpan dan langsung diterapkan pada kartu.', !j.err);
        })
        .catch(function(err){
            btn.disabled = false; btn.textContent = 'Simpan & Terapkan';
            showMsg('Gagal terhubung ke server: ' + err.message + '. Periksa koneksi lalu muat ulang halaman sebelum mencoba lagi.', false);
        });
    });

    refresh();
})();
</script>