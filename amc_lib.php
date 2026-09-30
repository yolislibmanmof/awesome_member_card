<?php
defined('INDEX_AUTH') OR die('Direct access not allowed!');

if (!function_exists('amc_settings')) {
    function amc_settings() {
        global $dbs;
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
        ];
        $res = $dbs->query("SELECT setting_name, setting_value FROM plugin_awesome_card_settings");
        if ($res) { while ($r = $res->fetch_assoc()) { $defaults[$r['setting_name']] = $r['setting_value']; } }
        return $defaults;
    }
}

if (!function_exists('amc_save_setting')) {
    function amc_save_setting($name, $value) {
        global $dbs;
        $chk = $dbs->prepare("SELECT id FROM plugin_awesome_card_settings WHERE setting_name = ?");
        $chk->bind_param('s', $name);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $st = $dbs->prepare("UPDATE plugin_awesome_card_settings SET setting_value = ? WHERE setting_name = ?");
            $st->bind_param('ss', $value, $name);
        } else {
            $st = $dbs->prepare("INSERT INTO plugin_awesome_card_settings (setting_name, setting_value) VALUES (?, ?)");
            $st->bind_param('ss', $name, $value);
        }
        $st->execute();
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
        if ($filename === '' || $filename === null) return null;
        $path = __DIR__ . '/assets/' . basename($filename);
        if (!is_file($path)) return null;
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml'][$ext] ?? null;
        if (!$mime) return null;
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }
}

if (!function_exists('amc_logo_data')) {
    function amc_logo_data($S) { return amc_asset_data($S['logo_file'] ?? ''); }
}
if (!function_exists('amc_sign_data')) {
    function amc_sign_data($S) { return amc_asset_data($S['sign_file'] ?? ''); }
}

/* ==== Pembersih nilai member_image (buang query/fragment) ==== */
if (!function_exists('amc_photo_clean')) {
    function amc_photo_clean($raw) {
        $raw = trim((string)$raw);
        if ($raw === '') return '';
        return preg_replace('/[?#].*$/', '', $raw);
    }
}

/* ==== Pencarian berkas foto anggota di disk server (toleran) ==== */
if (!function_exists('amc_member_photo_path')) {
    function amc_member_photo_path($member) {
        $raw = amc_photo_clean($member['member_image'] ?? '');
        if ($raw === '' || preg_match('#^https?://#i', $raw)) return null;
        $rel  = str_replace('\\', '/', $raw);
        $base = basename($rel);
        $root = dirname(__DIR__, 2);
        $roots = [];
        if (defined('IMAGES_BASE'))  $roots[] = rtrim(IMAGES_BASE, '/\\');
        if (defined('SENAYAN_BASE')) $roots[] = rtrim(SENAYAN_BASE, '/\\');
        $roots[] = $root;
        $subs = ['images/persons', 'images/members', 'images', ''];
        $tries = [];
        foreach ($roots as $r) {
            foreach ($subs as $s) {
                $prefix = $r . ($s === '' ? '' : '/' . $s);
                $tries[] = $prefix . '/' . $rel;
                $tries[] = $prefix . '/' . $base;
            }
        }
        foreach ($tries as $p) {
            $p = preg_replace('#/{2,}#', '/', $p);
            if (is_file($p)) return $p;
        }
        // Upaya terakhir: cocokkan nama tanpa memedulikan huruf besar/kecil
        $fname = pathinfo($base, PATHINFO_FILENAME);
        foreach ($roots as $r) {
            foreach (['images/persons', 'images/members', 'images'] as $s) {
                $dir = $r . '/' . $s . '/';
                if (!is_dir($dir)) continue;
                $f = glob($dir . $fname . '.*');
                if ($f) {
                    foreach ($f as $cand) {
                        $e = strtolower(pathinfo($cand, PATHINFO_EXTENSION));
                        if (in_array($e, ['jpg','jpeg','png','gif','webp'], true)) return $cand;
                    }
                }
            }
        }
        return null;
    }
}

/* ==== URL foto: proxy plugin, atau URL langsung bila tersimpan sebagai URL ==== */
if (!function_exists('amc_member_photo')) {
    function amc_member_photo($member) {
        $raw = amc_photo_clean($member['member_image'] ?? '');
        if ($raw === '') return null;
        if (preg_match('#^https?://#i', $raw)) return $raw;
        if (!amc_member_photo_path($member)) return null;
        $id = (int)($member['member_id'] ?? 0);
        return 'index.php?mod=membership&act=awesome_member_card&action=photo&member_id=' . $id;
    }
}

if (!function_exists('amc_container_url')) {
    function amc_container_url($file) {
        $id = md5(realpath($file));
        return (defined('AWB') ? AWB : '') . 'plugin_container.php?mod=membership&id=' . $id;
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