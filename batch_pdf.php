<?php
/**
 * Awesome Member Card — Ekspor PDF ukuran kartu (final 1.6.0)
 * Perbaikan: parse memory_limit benar (1G/-1 tidak diturunkan), guard fungsi,
 *            teks auto-fit + wrap (nama & ketentuan tidak meluber),
 *            tanggal konsisten d/m/Y, konversi WEBP/CMYK via GD, buffer bersih.
 */
defined('INDEX_AUTH') OR die('Direct access not allowed!');
global $dbs;
require_once __DIR__ . '/inc_card_render.php';
require_once __DIR__ . '/amc_lib.php';

$root      = dirname(__DIR__, 2);
$tcpdfPath = $root . '/lib/tcpdf/tcpdf.php';
if (!is_file($tcpdfPath)) {
    die('<div style="font-family:sans-serif;padding:40px">Pustaka TCPDF tidak ditemukan di <code>lib/tcpdf/</code>. Silakan gunakan mode cetak massal, lalu pilih "Save as PDF" pada dialog cetak peramban.</div>');
}
require_once $tcpdfPath;

/* ==== Utilitas ==== */
if (!function_exists('amc_pdf_bytes')) {
    function amc_pdf_bytes($v) {
        $v = trim((string)$v);
        if ($v === '' || $v === '-1') return -1;
        $n = (float)$v; $u = strtolower(substr($v, -1));
        if ($u === 'g') $n *= 1073741824; elseif ($u === 'm') $n *= 1048576; elseif ($u === 'k') $n *= 1024;
        return (int)$n;
    }
}
if (!function_exists('amc_hex2rgb')) {
    function amc_hex2rgb($hex) {
        $hex = ltrim((string)$hex, '#');
        if (strlen($hex) !== 6) return [30, 58, 138];
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
if (!function_exists('amc_asset_path')) {
    function amc_asset_path($S, $key) {
        $f = (string)($S[$key] ?? '');
        if ($f === '') return null;
        $p = __DIR__ . '/assets/' . basename($f);
        return is_file($p) ? $p : null;
    }
}
if (!function_exists('amc_pdf_date')) {
    function amc_pdf_date($raw) {
        $raw = trim((string)$raw);
        if ($raw === '' || strpos($raw, '0000') === 0) return '-';
        foreach (['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'd-m-Y'] as $fmt) {
            $d = DateTime::createFromFormat($fmt, $raw);
            if ($d instanceof DateTime) return $d->format('d/m/Y');
        }
        return $raw;
    }
}
/* Auto-fit: kecilkan huruf bila melebihi lebar, lalu elipsis bila tetap panjang */
if (!function_exists('amc_pdf_fit')) {
    function amc_pdf_fit($pdf, &$text, $maxW, $size, $min, $family, $style) {
        $text = (string)$text;
        $pdf->SetFont($family, $style, $size);
        if ($text === '' || $maxW <= 0) return;
        $w = $pdf->GetStringWidth($text);
        if ($w > $maxW && $w > 0) {
            $new = floor(($size * $maxW / $w) * 10) / 10;
            if ($new < $min) $new = $min;
            $pdf->SetFont($family, $style, $new);
            if ($pdf->GetStringWidth($text) > $maxW) {
                while (mb_strlen($text) > 1 && $pdf->GetStringWidth($text . '…') > $maxW) {
                    $text = mb_substr($text, 0, mb_strlen($text) - 1);
                }
                $text .= '…';
            }
        }
    }
}
if (!function_exists('amc_pdf_text')) {
    function amc_pdf_text($pdf, $x, $y, $text, $maxW, $size, $min = 3.0, $family = 'helvetica', $style = '') {
        amc_pdf_fit($pdf, $text, $maxW, $size, $min, $family, $style);
        $pdf->Text($x, $y, $text);
    }
}
/* Pemenggalan baris berbasis lebar nyata (mm) */
if (!function_exists('amc_pdf_wrap')) {
    function amc_pdf_wrap($pdf, $text, $maxW) {
        $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
        if ($text === '' || $maxW <= 0) return [];
        $lines = []; $cur = '';
        foreach (explode(' ', $text) as $w) {
            $try = ($cur === '') ? $w : $cur . ' ' . $w;
            if ($pdf->GetStringWidth($try) <= $maxW) { $cur = $try; continue; }
            if ($cur !== '') { $lines[] = $cur; $cur = ''; }
            while ($pdf->GetStringWidth($w) > $maxW && mb_strlen($w) > 1) {
                $cut = mb_strlen($w);
                while ($cut > 1 && $pdf->GetStringWidth(mb_substr($w, 0, $cut)) > $maxW) $cut--;
                $lines[] = mb_substr($w, 0, $cut);
                $w = mb_substr($w, $cut);
            }
            $cur = $w;
        }
        if ($cur !== '') $lines[] = $cur;
        return $lines;
    }
}
/* Gambar aman untuk TCPDF: WEBP/CMYK/tak dikenal -> PNG lewat GD (cache per berkas) */
if (!function_exists('amc_pdf_img')) {
    function amc_pdf_img($path) {
        if (!is_string($path) || $path === '' || !is_file($path)) return null;
        static $cache = [];
        if (array_key_exists($path, $cache)) return $cache[$path];
        $info = @getimagesize($path);
        $mime = (string)($info['mime'] ?? '');
        $safe = in_array($mime, ['image/jpeg', 'image/png', 'image/gif'], true);
        $cmyk = ($mime === 'image/jpeg' && isset($info['channels']) && (int)$info['channels'] === 4);
        if ($safe && !$cmyk) { $cache[$path] = $path; return $path; }
        if (!function_exists('imagecreatefromstring')) { $cache[$path] = $safe ? $path : null; return $cache[$path]; }
        $data = @file_get_contents($path);
        $im   = ($data !== false) ? @imagecreatefromstring($data) : false;
        if (!$im) { $cache[$path] = $safe ? $path : null; return $cache[$path]; }
        $dir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'amc_pdf';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $tmp = $dir . DIRECTORY_SEPARATOR . md5($path . '|' . (int)@filemtime($path)) . '.png';
        if (!is_file($tmp)) {
            $w = imagesx($im); $h = imagesy($im);
            $cv = imagecreatetruecolor($w, $h);
            imagealphablending($cv, false); imagesavealpha($cv, true);
            imagefill($cv, 0, 0, imagecolorallocatealpha($cv, 0, 0, 0, 127));
            imagecopy($cv, $im, 0, 0, 0, 0, $w, $h);
            @imagepng($cv, $tmp, 6);
            imagedestroy($cv);
        }
        imagedestroy($im);
        $cache[$path] = is_file($tmp) ? $tmp : ($safe ? $path : null);
        return $cache[$path];
    }
}

@set_time_limit(300);

$S = amc_settings();
$res = amc_batch_members($dbs, [
    'type'  => (int)($_GET['type'] ?? 0),
    'year'  => preg_match('/^\d{4}$/', (string)($_GET['year'] ?? '')) ? $_GET['year'] : '',
    'q'     => trim((string)($_GET['q'] ?? '')),
    'limit' => (int)($_GET['limit'] ?? 0),
]);
$rows = [];
if ($res) { while ($m = $res->fetch_assoc()) $rows[] = $m; }
if (empty($rows)) {
    die('<div style="font-family:sans-serif;padding:40px">Tidak ada data anggota yang cocok dengan filter yang dipilih.</div>');
}

/* Memori: naikkan bila perlu, JANGAN pernah menurunkan (1G / -1 aman) */
$need    = (count($rows) > 200) ? 512 : 256;
$cur     = amc_pdf_bytes(ini_get('memory_limit'));
if ($cur !== -1 && $cur < $need * 1048576) { @ini_set('memory_limit', $need . 'M'); }

$W = AMC_CW; $H = AMC_CH;
$pdf = new TCPDF('L', 'mm', [$W, $H], true, 'UTF-8', false);
$pdf->SetCreator('Awesome Member Card 1.6.0');
$pdf->SetAuthor($S['library_name'] ?? 'Perpustakaan');
$pdf->SetTitle('Kartu Anggota - ' . ($S['library_name'] ?? ''));
$pdf->SetSubject('Ekspor ' . count($rows) . ' kartu anggota');
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

foreach ($rows as $m) {
    $Sc    = amc_card_settings_for($S, $m);
    $id    = (int)($m['member_id'] ?? 0);
    $code  = (string)($m['member_code'] ?? ($m['member_card_id'] ?? ($m['member_number'] ?? (string)$id)));
    $name  = (string)($m['member_name'] ?? ($m['member_full_name'] ?? 'Anggota'));
    $phone = trim((string)($m['member_phone'] ?? ($m['phone'] ?? '')));
    $since = amc_pdf_date($m['member_since'] ?? ($m['register_date'] ?? ''));
    $years = max(1, min(10, (int)($Sc['valid_years'] ?? 2)));
    $valid = date('d/m/Y', strtotime('+' . $years . ' years'));
    $c1 = amc_hex2rgb($Sc['primary_color'] ?? '#1e3a8a');
    $c2 = amc_hex2rgb($Sc['secondary_color'] ?? '#3b82f6');
    $ca = amc_hex2rgb($Sc['accent_color'] ?? '#fbbf24');

    $showPhoto = ($Sc['show_photo'] ?? '1') === '1';
    $showQr    = ($Sc['show_qr'] ?? '1') === '1';
    $showSign  = ($Sc['show_signature'] ?? '1') === '1';
    $showChip  = ($Sc['show_chip'] ?? '1') === '1';

    $photoPath = $showPhoto ? amc_pdf_img(amc_member_photo_path($m)) : null;
    $logoPath  = amc_pdf_img(amc_asset_path($Sc, 'logo_file'));
    $signPath  = amc_pdf_img(amc_asset_path($Sc, 'sign_file'));

    /* ---------- SISI DEPAN ---------- */
    $pdf->AddPage();
    $pdf->LinearGradient(0, 0, $W, $H, $c1, $c2, ['x1' => 0, 'y1' => 0, 'x2' => 1, 'y2' => 1]);

    if ($logoPath) {
        $pdf->Image($logoPath, 5.5, 4.5, 7, 7, '', '', '', true, 300, '', false, false, 0, false, false, false);
    } else {
        $pdf->SetFillColor(255, 255, 255); $pdf->Rect(5.5, 4.5, 7, 7, 'F');
        $pdf->SetTextColor($c1[0], $c1[1], $c1[2]);
        amc_pdf_text($pdf, 6.9, 6.9, amc_initials($Sc['library_name'] ?? ''), 5.5, 8, 4, 'helvetica', 'B');
    }

    $pdf->SetTextColor(255, 255, 255);
    $brandW = $W - 15 - ($showChip ? 14 : 5);
    amc_pdf_text($pdf, 15, 5.6, mb_strtoupper((string)($Sc['library_name'] ?? '')), $brandW, 8.5, 4.5, 'helvetica', 'B');
    amc_pdf_text($pdf, 15, 9.8, mb_strtoupper((string)($Sc['card_subtitle'] ?? '')), $brandW, 5, 3, 'helvetica', '');

    if ($showChip) { $pdf->SetFillColor($ca[0], $ca[1], $ca[2]); $pdf->Rect($W - 12, 5, 7, 5, 'F'); }

    if ($photoPath) {
        $pdf->Image($photoPath, 5.5, 16, 17, 20, '', '', '', true, 300, '', false, false, 0, false, false, false);
    } else {
        $pdf->SetFillColor(255, 255, 255); $pdf->SetAlpha(0.16);
        $pdf->Rect(5.5, 16, 17, 20, 'F'); $pdf->SetAlpha(1);
        $pdf->SetTextColor(255, 255, 255);
        amc_pdf_text($pdf, 9.5, 24, amc_initials($name), 12, 17, 8, 'helvetica', 'B');
    }

    $whoW = $W - 26 - 5;
    $pdf->SetTextColor(255, 255, 255);
    amc_pdf_text($pdf, 26, 17, mb_strtoupper($name), $whoW, 11.5, 5.5, 'helvetica', 'B');
    amc_pdf_text($pdf, 26, 23.5, $code, $whoW, 7.5, 4, 'courier', 'B');

    $pdf->SetAlpha(0.75);
    amc_pdf_text($pdf, 26, 28.5, 'ID ANGGOTA', 26, 5.2, 3.5);
    amc_pdf_text($pdf, 56, 28.5, 'TERDAFTAR', $whoW - 26, 5.2, 3.5);
    $pdf->SetAlpha(1);
    amc_pdf_text($pdf, 26, 31.5, (string)$id, 26, 6.2, 4);
    amc_pdf_text($pdf, 56, 31.5, $since, $whoW - 26, 6.2, 4);

    if ($phone !== '') {
        $pdf->SetAlpha(0.75); amc_pdf_text($pdf, 26, 35, 'TELEPON', $whoW, 5.2, 3.5);
        $pdf->SetAlpha(1);    amc_pdf_text($pdf, 26, 38, $phone, $whoW, 6.2, 4);
    }

    if ($showSign) {
        if ($signPath) { $pdf->Image($signPath, 5.5, 41, 13, 7, '', '', '', true, 300, '', false, false, 0, false, false, false); }
        $pdf->SetDrawColor(255, 255, 255); $pdf->Line(5.5, 51, 31.5, 51);
        $signTxt = trim(((($Sc['sign_name'] ?? '') !== '') ? $Sc['sign_name'] . ' | ' : '') . ($Sc['sign_title'] ?? '') . ' - ' . ($Sc['library_name'] ?? ''));
        amc_pdf_text($pdf, 5.5, 52.2, $signTxt, 30, 5.2, 3.0);
    }

    if ($showQr) {
        $pdf->SetFillColor(255, 255, 255); $pdf->Rect($W - 21, 40, 16, 16, 'F');
        $pdf->write2DBarcode($code, 'QRCODE,M', $W - 20, 41, 14, 14, ['bgcolor' => [255, 255, 255], 'fgcolor' => [15, 23, 42]], 'N');
        $pdf->SetTextColor(15, 23, 42);
        amc_pdf_text($pdf, $W - 19.5, 56.6, $code, 15, 4, 2.5, 'courier', 'B');
    }

    $pdf->SetTextColor(255, 255, 255); $pdf->SetAlpha(0.72);
    amc_pdf_text($pdf, 5.5, $H - 3, (string)($Sc['footer_text'] ?? ''), $W - 11, 4.6, 3.0);
    $pdf->SetAlpha(1);

    /* ---------- SISI BELAKANG ---------- */
    $pdf->AddPage();
    $pdf->LinearGradient(0, 0, $W, $H, $c1, $c2, ['x1' => 0, 'y1' => 0, 'x2' => 1, 'y2' => 1]);
    $pdf->SetFillColor(11, 18, 32); $pdf->Rect(0, 0, $W, 7, 'F');
    $pdf->SetFillColor($ca[0], $ca[1], $ca[2]); $pdf->Rect(0, 7, $W, 0.6, 'F');
    $pdf->SetFillColor(255, 255, 255); $pdf->Rect(5.5, 11, 42, 13, 'F');

    if ($signPath) { $pdf->Image($signPath, 19, 12, 15, 8, '', '', '', true, 300, '', false, false, 0, false, false, false); }

    $pdf->SetTextColor(100, 116, 139);
    amc_pdf_text($pdf, 11, 22.3, 'TANDA TANGAN ' . mb_strtoupper((string)($Sc['sign_title'] ?? '')), 38, 4.6, 3.0);

    $pdf->SetFillColor($ca[0], $ca[1], $ca[2]);
    $pdf->RoundedRect($W - 29, 11, 24, 4.5, 1.2, '1111', 'F');
    $pdf->SetTextColor(30, 41, 59);
    amc_pdf_text($pdf, $W - 27, 12.6, 'BERLAKU S/D', 21, 5, 3.5, 'helvetica', 'B');
    $pdf->SetTextColor(255, 255, 255);
    amc_pdf_text($pdf, $W - 29, 18.5, $valid, 26, 10.5, 6, 'helvetica', 'B');
    $pdf->SetAlpha(0.85);
    amc_pdf_text($pdf, $W - 29, 23.5, 'Diterbitkan: ' . date('d/m/Y') . '  |  ID ' . $id, 27, 4.8, 3.0);
    $pdf->SetAlpha(1);

    $pdf->SetTextColor($ca[0], $ca[1], $ca[2]);
    amc_pdf_text($pdf, 5.5, 28, 'KETENTUAN PENGGUNAAN KARTU', $W - 11, 5, 3.5, 'helvetica', 'B');

    /* Ketentuan: WRAP nyata, berhenti sebelum menabrak kaki kartu */
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', '', 4.8);
    $terms   = array_slice(preg_split('/\r?\n/', (string)($Sc['card_terms'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), 0, 6);
    $termW   = $W - 13;
    $y       = 31.0;
    $lineH   = 2.6;
    $maxY    = $H - 11.5;
    foreach ($terms as $i => $t) {
        $lines = amc_pdf_wrap($pdf, ($i + 1) . '. ' . trim((string)$t), $termW);
        foreach ($lines as $ln) {
            if ($y > $maxY) break 2;
            $pdf->Text(6.5, $y, $ln);
            $y += $lineH;
        }
    }

    if ($logoPath) { $pdf->Image($logoPath, 5.5, $H - 9, 5.5, 5.5, '', '', '', true, 300, '', false, false, 0, false, false, false); }
    $pdf->SetTextColor(255, 255, 255);
    amc_pdf_text($pdf, 13, $H - 7.2, mb_strtoupper((string)($Sc['library_name'] ?? '')), $W - 13 - 28, 6, 3.5, 'helvetica', 'B');
    amc_pdf_text($pdf, $W - 26, $H - 7.2, $code, 21, 5.5, 3.0, 'courier', 'B');
}

/* Bersihkan buffer agar PDF tidak korup karena notice/whitespace */
while (ob_get_level() > 0) { @ob_end_clean(); }
if (!headers_sent()) { @header_remove('Content-Type'); }
$pdf->Output('kartu-anggota-' . date('Ymd-His') . '.pdf', 'I');