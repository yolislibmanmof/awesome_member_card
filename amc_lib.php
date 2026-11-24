<?php
/**
 * Awesome Member Card — Pustaka inti (final 1.6.0)
 * Perubahan: cache settings, UPSERT anti-duplikat, migrasi malas (lazy),
 * cache resolver foto + index folder, proteksi logo saat unggah gagal,
 * helper foto bertanda tangan HMAC + ETag/304.
 * CATATAN: jangan tambahkan amc_hex2rgb / amc_asset_path / amc_safe_color di sini,
 * karena nama itu dideklarasikan TANPA guard di batch_pdf.php & card_page.php.
 */
defined('INDEX_AUTH') OR die('Direct access not allowed!');

/* ==== Penyimpanan statis bersama (hemat query & operasi filesystem) ==== */
if (!function_exists('amc_store')) {
    function &amc_store() {
        static $s = [
            'settings' => null,
            'table'    => false,
            'photo'    => [],
            'index'    => [],
            'asset'    => [],
            'key'      => null,
            'helper'   => false,
        ];
        return $s;
    }
}

/* ==== Penjamin tabel: migrasi mandiri maksimal 1x per request ==== */
if (!function_exists('amc_ensure_table')) {
    function amc_ensure_table() {
        $st = &amc_store();
        if ($st['table']) return;
        $st['table'] = true;
        require_once __DIR__ . '/migration.php';
        if (function_exists('run_awesome_card_migration')) run_awesome_card_migration();
    }
}

if (!function_exists('amc_settings')) {
    function amc_settings($refresh = false) {
        global $dbs;
        $st = &amc_store();
        if (!$refresh && $st['settings'] !== null) return $st['settings'];

        $defaults = [
            'library_name'   => 'Perpustakaan Digital',
            'card_subtitle'  => 'Library Member Card',
            'primary_color'  => '#1e3a8a',
            'secondary_color'=> '#3b82f6',
            'accent_color'   => '#fbbf24',
            'card_style'     => 'aurora',
            'card_font'      => 'modern',
            'show_photo'     => '1',
            'show_qr'        => '1',
            'show_signature' => '1',
            'show_holo'      => '1',
            'show_chip'      => '1',
            'valid_years'    => '2',
            'footer_text'    => 'Kartu ini adalah properti resmi perpustakaan.',
            'card_terms'     => "Kartu ini merupakan identitas resmi anggota perpustakaan.\nWajib ditunjukkan saat peminjaman dan pengembalian koleksi.\nKartu tidak dapat dipindahtangankan kepada orang lain.\nKehilangan kartu harap segera dilaporkan kepada petugas sirkulasi.\nMasa berlaku kartu tertera pada bagian belakang ini.",
            'sign_title'     => 'Kepala Perpustakaan',
            'sign_name'      => '',
            'logo_file'      => '',
            'sign_file'      => '',
            'type_templates' => '',
            'photo_key'      => '',
        ];

        $read = function () use ($dbs) {
            if (!isset($dbs) || !is_object($dbs)) return false;
            try {
                $res = $dbs->query("SELECT setting_name, setting_value FROM plugin_awesome_card_settings");
            } catch (\Throwable $e) { return false; }
            if (!$res) return false;
            $out = [];
            while ($r = $res->fetch_assoc()) $out[$r['setting_name']] = $r['setting_value'];
            return $out;
        };

        $rows = $read();
        if ($rows === false) { amc_ensure_table(); $rows = $read(); }
        if (is_array($rows)) $defaults = array_merge($defaults, $rows);
        $st['settings'] = $defaults;
        return $defaults;
    }
}

if (!function_exists('amc_save_setting')) {
    function amc_save_setting($name, $value) {
        global $dbs;
        if (!isset($dbs) || !is_object($dbs)) return false;
        $name  = (string)$name;
        $value = (string)$value;

        // PROTEKSI: jangan hapus logo/tanda tangan bila unggahan baru GAGAL.
        // Penghapusan hanya boleh lewat checkbox logo_remove / sign_remove.
        if ($value === '' && ($name === 'logo_file' || $name === 'sign_file')) {
            $field = ($name === 'logo_file') ? 'logo' : 'sign';
            if (!empty($_FILES[$field]['name']) && empty($_POST[$field . '_remove'])) return false;
        }

        $write = function () use ($dbs, $name, $value) {
            try {
                $sql = "INSERT INTO plugin_awesome_card_settings (setting_name, setting_value) VALUES (?, ?) "
                     . "ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
                $s = $dbs->prepare($sql);
                if (!$s) return false;
                $s->bind_param('ss', $name, $value);
                $ok = $s->execute();
                $s->close();
                return (bool)$ok;
            } catch (\Throwable $e) { return false; }
        };

        $ok = $write();
        if (!$ok) { amc_ensure_table(); $ok = $write(); }
        if ($ok) {
            $st = &amc_store();
            if (is_array($st['settings'])) $st['settings'][$name] = $value;
        }
        return $ok;
    }
}

if (!function_exists('amc_initials')) {
    function amc_initials($name) {
        $p = preg_split('/\s+/', trim((string)$name));
        $a = mb_strtoupper(mb_substr($p[0] ?? 'A', 0, 1));
        $b = isset($p[1]) ? mb_strtoupper(mb_substr($p[1], 0, 1)) : '';
        return $a . $b;
    }
}

if (!function_exists('amc_asset_data')) {
    function amc_asset_data($filename) {
        $filename = (string)$filename;
        if ($filename === '') return null;
        $key = basename($filename);
        $st = &amc_store();
        if (array_key_exists($key, $st['asset'])) return $st['asset'][$key];
        $out  = null;
        $path = __DIR__ . '/assets/' . $key;
        if (is_file($path) && (int)@filesize($path) <= 2097152) {
            $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml'][$ext] ?? null;
            if ($mime) {
                $raw = @file_get_contents($path);
                if ($raw !== false) $out = 'data:' . $mime . ';base64,' . base64_encode($raw);
            }
        }
        $st['asset'][$key] = $out;
        return $out;
    }
}

if (!function_exists('amc_logo_data')) {
    function amc_logo_data($S) { return amc_asset_data($S['logo_file'] ?? ''); }
}
if (!function_exists('amc_sign_data')) {
    function amc_sign_data($S) { return amc_asset_data($S['sign_file'] ?? ''); }
}

if (!function_exists('amc_photo_clean')) {
    function amc_photo_clean($raw) {
        $raw = trim((string)$raw);
        if ($raw === '') return '';
        return preg_replace('/[?#].*$/', '', $raw);
    }
}

/* ==== Index nama berkas per folder (dibangun sekali per request) ==== */
if (!function_exists('amc_photo_index')) {
    function amc_photo_index($dir) {
        $st  = &amc_store();
        $key = rtrim(str_replace('\\', '/', (string)$dir), '/');
        if (array_key_exists($key, $st['index'])) return $st['index'][$key];
        $map = [];
        if ($key !== '' && is_dir($key)) {
            try {
                $it = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($key, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($it as $f) {
                    if (!$f->isFile()) continue;
                    $e = strtolower($f->getExtension());
                    if (!in_array($e, ['jpg','jpeg','png','gif','webp'], true)) continue;
                    $lk = strtolower($f->getFilename());
                    if (!isset($map[$lk])) $map[$lk] = $f->getPathname();
                }
            } catch (\Throwable $e) { /* abaikan folder tak terbaca */ }
        }
        $st['index'][$key] = $map;
        return $map;
    }
}

if (!function_exists('amc_member_photo_path')) {
    function amc_member_photo_path($member) {
        $raw = amc_photo_clean($member['member_image'] ?? '');
        if ($raw === '' || preg_match('#^https?://#i', $raw)) return null;

        $st = &amc_store();
        if (array_key_exists($raw, $st['photo'])) return $st['photo'][$raw]; // termasuk hasil negatif

        $rel   = str_replace('\\', '/', $raw);
        $base  = basename($rel);
        $fname = pathinfo($base, PATHINFO_FILENAME);
        $validExt = ['jpg','jpeg','png','gif','webp'];

        $roots = [];
        if (defined('SB'))           $roots[] = rtrim(SB, '/\\');
        if (defined('IMAGES_BASE'))  $roots[] = rtrim(IMAGES_BASE, '/\\');
        if (defined('SENAYAN_BASE')) $roots[] = rtrim(SENAYAN_BASE, '/\\');
        $roots[] = dirname(__DIR__, 2);
        $roots[] = dirname(__DIR__, 3);
        $roots = array_values(array_unique(array_filter($roots, function ($r) { return $r !== '' && is_dir($r); })));
        $subs = ['images/persons', 'images/members', 'images', ''];

        $found = null;
        foreach ($roots as $r) {
            foreach ($subs as $s) {
                $prefix = $r . ($s === '' ? '' : '/' . $s);
                $p1 = preg_replace('#/{2,}#', '/', $prefix . '/' . $rel);
                if (is_file($p1)) { $found = $p1; break 2; }
                $p2 = preg_replace('#/{2,}#', '/', $prefix . '/' . $base);
                if (is_file($p2)) { $found = $p2; break 2; }
            }
        }
        if (!$found) {
            foreach ($roots as $r) {
                foreach ($subs as $s) {
                    $dir = $r . ($s === '' ? '' : '/' . $s) . '/';
                    if (!is_dir($dir)) continue;
                    foreach ((array)glob($dir . $fname . '.*') as $cand) {
                        $e = strtolower(pathinfo($cand, PATHINFO_EXTENSION));
                        if (in_array($e, $validExt, true)) { $found = $cand; break 3; }
                    }
                }
            }
        }
        if (!$found) {
            $lk = strtolower($base);
            foreach ($roots as $r) {
                $map = amc_photo_index($r . '/images'); // satu sapuan untuk SEMUA anggota
                if (isset($map[$lk])) { $found = $map[$lk]; break; }
            }
        }
        $st['photo'][$raw] = $found;
        return $found;
    }
}

/* ==== Kunci & token foto (HMAC) ==== */
if (!function_exists('amc_photo_key')) {
    function amc_photo_key() {
        $st = &amc_store();
        if ($st['key'] !== null) return $st['key'];
        $S = amc_settings();
        $k = (string)($S['photo_key'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/', $k)) {
            $k = bin2hex(random_bytes(32));
            amc_save_setting('photo_key', $k);
            $st['helper'] = false; // kunci baru -> helper perlu ditanam ulang
        }
        $st['key'] = $k;
        return $k;
    }
}
if (!function_exists('amc_photo_token')) {
    function amc_photo_token($raw) { return substr(hash_hmac('sha256', 'f:' . (string)$raw, amc_photo_key()), 0, 32); }
}
if (!function_exists('amc_id_token')) {
    function amc_id_token($id) { return substr(hash_hmac('sha256', 'id:' . (int)$id, amc_photo_key()), 0, 32); }
}

/* ==== Sumber helper foto v2 (HMAC + ETag + anti path traversal) ==== */
if (!function_exists('amc_photo_helper_source')) {
    function amc_photo_helper_source() {
        return <<<'AMCHELPER'
<?php
/* AMC Photo Helper v2 | __AMC_MARK__
 * Dibuat otomatis oleh plugin Awesome Member Card. Ringan, tanpa bootstrap SLiMS.
 * Hanya melayani berkas bertanda tangan HMAC (?k=) di dalam folder instalasi.
 */
error_reporting(0);
$KEY  = '__AMC_KEY__';
$orig = isset($_GET['f']) ? (string)$_GET['f'] : '';
$tok  = isset($_GET['k']) ? (string)$_GET['k'] : '';
if ($orig === '' || strlen($orig) > 220 || preg_match('/[\x00-\x1F\x7F]/', $orig)) { http_response_code(404); exit; }
if ($KEY !== '' && !hash_equals(substr(hash_hmac('sha256', 'f:' . $orig, $KEY), 0, 32), $tok)) { http_response_code(403); exit; }
$f = str_replace('\\', '/', $orig);
$f = preg_replace('/[?#].*$/', '', $f);
$f = ltrim(preg_replace('#/{2,}#', '/', $f), '/');
if ($f === '' || strpos($f, '..') !== false) { http_response_code(404); exit; }
if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $f)) { http_response_code(404); exit; }
$base = __DIR__;
$rootReal = realpath($base);
if ($rootReal === false) { http_response_code(404); exit; }
$rootN = rtrim(str_replace('\\', '/', $rootReal), '/');
$name  = basename($f);
$stem  = pathinfo($name, PATHINFO_FILENAME);
$inside = function ($p) use ($rootN) {
    if (!is_file($p) || !is_readable($p)) return null;
    $rp = realpath($p);
    if ($rp === false) return null;
    $rpN = str_replace('\\', '/', $rp);
    return (strpos($rpN, $rootN . '/') === 0) ? $rp : null;
};
$found = null;
$cands = [$base . '/images/' . $f, $base . '/' . $f, $base . '/images/persons/' . $name, $base . '/images/members/' . $name, $base . '/images/' . $name];
foreach ($cands as $c) { $found = $inside($c); if ($found) break; }
if (!$found) {
    foreach (['/images/persons/', '/images/members/', '/images/'] as $d) {
        $dir = $base . $d;
        if (!is_dir($dir)) continue;
        foreach ((array)glob($dir . $stem . '.*') as $g) {
            if (preg_match('/\.(jpe?g|png|gif|webp)$/i', $g)) { $found = $inside($g); if ($found) break 2; }
        }
    }
}
if (!$found) { http_response_code(404); exit; }
$size = (int)@filesize($found);
$mt   = (int)@filemtime($found);
$etag = '"' . md5($found . '|' . $size . '|' . $mt) . '"';
$ext  = strtolower(pathinfo($found, PATHINFO_EXTENSION));
$mime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'][$ext] ?? 'image/jpeg';
header('X-Content-Type-Options: nosniff');
header('ETag: ' . $etag);
if ($mt > 0) header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mt) . ' GMT');
header('Cache-Control: private, max-age=86400');
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim((string)$_SERVER['HTTP_IF_NONE_MATCH']) === $etag) { http_response_code(304); exit; }
header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
readfile($found);
exit;
AMCHELPER;
    }
}

/* ==== Tanam/perbarui helper sekali per request ==== */
if (!function_exists('amc_photo_helper_file')) {
    function amc_photo_helper_file() {
        $st = &amc_store();
        if ($st['helper'] !== false) return $st['helper'];
        $st['helper'] = null;
        $root = dirname(__DIR__, 2);
        if (!is_dir($root)) return null;
        $file   = $root . '/amc_photo.php';
        $key    = amc_photo_key();
        $marker = 'AMC-HELPER-v2:' . substr(md5($key), 0, 12);
        $need   = true;
        if (is_file($file)) {
            $cur  = (string)@file_get_contents($file, false, null, 0, 4096);
            $need = (strpos($cur, $marker) === false);
        }
        if ($need) {
            $src = str_replace(['__AMC_KEY__', '__AMC_MARK__'], [$key, $marker], amc_photo_helper_source());
            @file_put_contents($file, $src); // bila gagal, helper lama tetap dipakai (token diabaikan)
        }
        $st['helper'] = is_file($file) ? $file : null;
        return $st['helper'];
    }
}

/* ==== URL foto: helper ringan di akar, proxy admin sebagai cadangan ==== */
if (!function_exists('amc_member_photo')) {
    function amc_member_photo($member) {
        $raw = amc_photo_clean($member['member_image'] ?? '');
        if ($raw === '') return null;
        if (preg_match('#^https?://#i', $raw)) return $raw;
        if (!amc_member_photo_path($member)) return null;
        $id = (int)($member['member_id'] ?? 0);

        $web = defined('SWB') ? SWB : '';
        if ($web === '' && defined('AWB')) $web = AWB;
        if ($web !== '' && substr($web, -1) !== '/') $web .= '/';
        if ($web !== '' && preg_match('#/admin/$#', $web)) $web = substr($web, 0, -7) . '/';

        if (amc_photo_helper_file()) {
            $prefix = ($web !== '') ? $web : '../';
            return $prefix . 'amc_photo.php?f=' . rawurlencode($raw) . '&k=' . amc_photo_token($raw);
        }

        $base = $web;
        if ($base === '') {
            $uri  = $_SERVER['REQUEST_URI'] ?? '/';
            $pos  = strpos($uri, '/admin/');
            $base = ($pos !== false) ? substr($uri, 0, $pos + 7) : '/admin/';
        }
        return $base . 'index.php?mod=membership&act=awesome_member_card&action=photo&member_id=' . $id . '&k=' . amc_id_token($id);
    }
}

if (!function_exists('amc_container_url')) {
    function amc_container_url($file) {
        $id = md5(realpath($file)); // identik dengan registerMenu() SLiMS 9.8
        return (defined('AWB') ? AWB : '') . 'plugin_container.php?mod=membership&id=' . $id;
    }
}

/* ==== Basis situs absolut (dipakai halaman & email ucapan) ==== */
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

/* ===== Generator QR Code murni PHP (Byte mode, EC-M, versi 1-6, mask 0) ===== */
if (!function_exists('amc_qr_svg')) {
    function amc_qr_svg($text, $css_size = '12mm', $fg = '#0f172a')
    {
        static $EXP = null, $LOG = null;
        if ($EXP === null) {
            $EXP = array_fill(0, 512, 0); $LOG = array_fill(0, 256, 0);
            $x = 1;
            for ($i = 0; $i < 255; $i++) { $EXP[$i] = $x; $LOG[$x] = $i; $x <<= 1; if ($x & 0x100) $x ^= 0x11D; }
            for ($i = 255; $i < 512; $i++) $EXP[$i] = $EXP[$i - 255];
        }
        $gmul = function ($a, $b) use ($EXP, $LOG) { return ($a == 0 || $b == 0) ? 0 : $EXP[$LOG[$a] + $LOG[$b]]; };

        $data = (string)$text;
        $vers = [1 => [16,1,10], 2 => [28,1,16], 3 => [44,1,26], 4 => [64,2,18], 5 => [86,2,24], 6 => [108,4,16]];
        $v = 6;
        foreach ($vers as $i => $c) { if (strlen($data) + 2 <= $c[0]) { $v = $i; break; } }
        [$dc, $nb, $ec] = $vers[$v];
        if (strlen($data) + 2 > $dc) $data = substr($data, 0, $dc - 2);
        $len = strlen($data);

        $bits = '0100' . str_pad(decbin($len), 8, '0', STR_PAD_LEFT);
        for ($i = 0; $i < $len; $i++) $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        $bits .= substr('0000', 0, min(4, $dc * 8 - strlen($bits)));
        while (strlen($bits) % 8) $bits .= '0';
        $bytes = array_map('bindec', str_split($bits, 8));
        $pi = 0;
        while (count($bytes) < $dc) { $bytes[] = ($pi++ % 2) ? 0x11 : 0xEC; }

        $gen = [1];
        for ($i = 0; $i < $ec; $i++) {
            $ng = array_fill(0, count($gen) + 1, 0);
            for ($j = 0; $j < count($gen); $j++) { $ng[$j] ^= $gen[$j]; $ng[$j + 1] ^= $gmul($gen[$j], $EXP[$i]); }
            $gen = $ng;
        }

        $blocks = []; $eccs = []; $off = 0; $bl = intdiv($dc, $nb);
        for ($b = 0; $b < $nb; $b++) {
            $bd = array_slice($bytes, $off, $bl); $off += $bl;
            $msg = array_merge($bd, array_fill(0, $ec, 0));
            for ($i = 0; $i < count($bd); $i++) {
                $coef = $msg[$i];
                if ($coef != 0) { for ($j = 0; $j < count($gen); $j++) $msg[$i + $j] ^= $gmul($gen[$j], $coef); }
            }
            $blocks[] = $bd; $eccs[] = array_slice($msg, count($bd));
        }
        $final = [];
        for ($i = 0; $i < $bl; $i++) foreach ($blocks as $bd) $final[] = $bd[$i];
        for ($i = 0; $i < $ec; $i++) foreach ($eccs as $e) $final[] = $e[$i];
        $allbits = '';
        foreach ($final as $byte) $allbits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);

        $size = 17 + 4 * $v;
        $mod = array_fill(0, $size, array_fill(0, $size, 0));
        $fn  = array_fill(0, $size, array_fill(0, $size, false));
        $setF = function ($r, $c, $val) use (&$mod, &$fn, $size) {
            if ($r >= 0 && $c >= 0 && $r < $size && $c < $size) { $mod[$r][$c] = $val ? 1 : 0; $fn[$r][$c] = true; }
        };
        foreach ([[0,0],[0,$size-7],[$size-7,0]] as $f) {
            for ($i = -1; $i <= 7; $i++) for ($j = -1; $j <= 7; $j++) {
                $in = ($i >= 0 && $i <= 6 && $j >= 0 && $j <= 6);
                $dark = $in && (($i == 0 || $i == 6 || $j == 0 || $j == 6) || ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4));
                $setF($f[0] + $i, $f[1] + $j, $dark);
            }
        }
        for ($i = 8; $i < $size - 8; $i++) { $setF(6, $i, $i % 2 == 0); $setF($i, 6, $i % 2 == 0); }
        $ap = [2 => [6,18], 3 => [6,22], 4 => [6,26], 5 => [6,30], 6 => [6,34]][$v] ?? [];
        foreach ($ap as $r) foreach ($ap as $c) {
            if (($r == 6 && $c == 6) || ($r == 6 && $c == $size - 7) || ($r == $size - 7 && $c == 6)) continue;
            for ($i = -2; $i <= 2; $i++) for ($j = -2; $j <= 2; $j++) $setF($r + $i, $c + $j, max(abs($i), abs($j)) != 1);
        }
        $fmtPos1 = [];
        for ($i = 0; $i <= 5; $i++) $fmtPos1[] = [8, $i];
        $fmtPos1[] = [8,7]; $fmtPos1[] = [8,8]; $fmtPos1[] = [7,8];
        for ($i = 9; $i < 15; $i++) $fmtPos1[] = [14 - $i, 8];
        $fmtPos2 = [];
        for ($i = 0; $i < 8; $i++) $fmtPos2[] = [$size - 1 - $i, 8];
        for ($i = 8; $i < 15; $i++) $fmtPos2[] = [8, $size - 15 + $i];
        foreach (array_merge($fmtPos1, $fmtPos2) as $p) $setF($p[0], $p[1], 0);
        $setF($size - 8, 8, 1);

        $i = 0; $total = strlen($allbits);
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right == 6) $right = 5;
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = ((($right + 1) & 2) == 0);
                    $y = $upward ? $size - 1 - $vert : $vert;
                    if (!$fn[$y][$x] && $i < $total) { $mod[$y][$x] = (int)$allbits[$i]; $i++; }
                }
            }
        }
        for ($y = 0; $y < $size; $y++) for ($x = 0; $x < $size; $x++)
            if (!$fn[$y][$x] && (($x + $y) % 2 == 0)) $mod[$y][$x] ^= 1;
        $fb = 0x5412;
        for ($k = 0; $k < 15; $k++) {
            $bit = ($fb >> $k) & 1;
            $mod[$fmtPos1[$k][0]][$fmtPos1[$k][1]] = $bit;
            $mod[$fmtPos2[$k][0]][$fmtPos2[$k][1]] = $bit;
        }

        $q = 4; $dim = $size + 2 * $q;
        $out = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $dim . ' ' . $dim . '" style="width:' . $css_size . ';height:' . $css_size . ';display:block">';
        $out .= '<rect width="' . $dim . '" height="' . $dim . '" fill="#ffffff"/>';
        for ($y = 0; $y < $size; $y++) for ($x = 0; $x < $size; $x++)
            if ($mod[$y][$x]) $out .= '<rect x="' . ($x + $q) . '" y="' . ($y + $q) . '" width="1" height="1" fill="' . $fg . '"/>';
        return $out . '</svg>';
    }
}