<?php
global $dbs;
require_once __DIR__ . '/inc_card_render.php';

$root = dirname(__DIR__, 2);
$tcpdfPath = $root . '/lib/tcpdf/tcpdf.php';
if (!is_file($tcpdfPath)) {
    die('<div style="font-family:sans-serif;padding:40px">Pustaka TCPDF tidak ditemukan di <code>lib/tcpdf/</code>. Silakan gunakan mode cetak massal, lalu pilih "Save as PDF" pada dialog cetak peramban.</div>');
}
require_once $tcpdfPath;

$S = amc_settings();
$res = amc_batch_members($dbs, [
    'type'  => (int)($_GET['type'] ?? 0),
    'year'  => preg_match('/^\d{4}$/', (string)($_GET['year'] ?? '')) ? $_GET['year'] : '',
    'q'     => trim((string)($_GET['q'] ?? '')),
    'limit' => (int)($_GET['limit'] ?? 0),
]);
$rows = [];
if ($res) { while ($m = $res->fetch_assoc()) $rows[] = $m; }

function amc_hex2rgb($hex) {
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) !== 6) return [30, 58, 138];
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}
function amc_fs_path($member, $root) {
    $img = $member['member_image'] ?? null;
    if (!$img) return null;
    if (is_file($root . '/images/' . $img)) return $root . '/images/' . $img;
    if (is_file($root . '/images/persons/' . $img)) return $root . '/images/persons/' . $img;
    return null;
}
function amc_asset_path($S, $key) {
    $f = $S[$key] ?? '';
    if ($f === '') return null;
    $p = __DIR__ . '/assets/' . basename($f);
    return is_file($p) ? $p : null;
}

$W = AMC_CW; $H = AMC_CH;
$pdf = new TCPDF('L', 'mm', [$W, $H], true, 'UTF-8', false);
$pdf->SetCreator('Awesome Member Card');
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

foreach ($rows as $m) {
    $Sc = amc_card_settings_for($S, $m);
    $id    = (int)($m['member_id'] ?? 0);
    $code  = (string)($m['member_code'] ?? ($m['member_card_id'] ?? ($m['member_number'] ?? (string)$id)));
    $name  = (string)($m['member_name'] ?? ($m['member_full_name'] ?? 'Anggota'));
    $phone = trim((string)($m['member_phone'] ?? ($m['phone'] ?? '')));
    $since = trim((string)($m['member_since'] ?? ($m['register_date'] ?? '')));
    if ($since === '' || strpos($since, '0000') === 0) $since = '-';
    $valid = date('d/m/Y', strtotime('+' . (int)$Sc['valid_years'] . ' years'));
    $c1 = amc_hex2rgb($Sc['primary_color']); $c2 = amc_hex2rgb($Sc['secondary_color']); $ca = amc_hex2rgb($Sc['accent_color']);

    /* ---- SISI DEPAN ---- */
    $pdf->AddPage();
    $pdf->LinearGradient(0, 0, $W, $H, $c1, $c2, ['x1' => 0, 'y1' => 0, 'x2' => 1, 'y2' => 1]);
    $logoPath = amc_asset_path($Sc, 'logo_file');
    if ($logoPath) { $pdf->Image($logoPath, 5.5, 4.5, 7, 7, '', '', '', true, 300, '', false, false, 0, false, false, false); }
    else {
        $pdf->SetFillColor(255, 255, 255); $pdf->Rect(5.5, 4.5, 7, 7, 'F');
        $pdf->SetTextColor($c1[0], $c1[1], $c1[2]); $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Text(6.9, 6.9, amc_initials($Sc['library_name']));
    }
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 8.5);
    $pdf->Text(15, 5.6, mb_strtoupper($Sc['library_name']));
    $pdf->SetFont('helvetica', '', 5);
    $pdf->Text(15, 9.8, mb_strtoupper($Sc['card_subtitle']));
    if ($Sc['show_chip'] === '1') { $pdf->SetFillColor($ca[0], $ca[1], $ca[2]); $pdf->Rect($W - 12, 5, 7, 5, 'F'); }
    $photoPath = ($Sc['show_photo'] === '1') ? amc_fs_path($m, $root) : null;
    if ($photoPath) { $pdf->Image($photoPath, 5.5, 16, 17, 20, '', '', '', true, 300, '', false, false, 0, false, false, false); }
    else {
        $pdf->SetFillColor(255, 255, 255); $pdf->SetAlpha(0.16); $pdf->Rect(5.5, 16, 17, 20, 'F'); $pdf->SetAlpha(1);
        $pdf->SetTextColor(255, 255, 255); $pdf->SetFont('helvetica', 'B', 17);
        $pdf->Text(9.5, 24, amc_initials($name));
    }
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 11.5);
    $pdf->Text(26, 17, mb_strtoupper($name));
    $pdf->SetFont('courier', 'B', 7.5);
    $pdf->Text(26, 23.5, $code);
    $pdf->SetFont('helvetica', '', 5.2); $pdf->SetAlpha(0.75);
    $pdf->Text(26, 28.5, 'ID ANGGOTA'); $pdf->Text(56, 28.5, 'TERDAFTAR');
    $pdf->SetAlpha(1); $pdf->SetFont('helvetica', '', 6.2);
    $pdf->Text(26, 31.5, (string)$id); $pdf->Text(56, 31.5, $since);
    if ($phone !== '') { $pdf->SetAlpha(0.75); $pdf->SetFont('helvetica', '', 5.2); $pdf->Text(26, 35, 'TELEPON'); $pdf->SetAlpha(1); $pdf->SetFont('helvetica', '', 6.2); $pdf->Text(26, 38, $phone); }
    $signPath = amc_asset_path($Sc, 'sign_file');
    if ($Sc['show_signature'] === '1') {
        if ($signPath) { $pdf->Image($signPath, 5.5, 41, 13, 7, '', '', '', true, 300, '', false, false, 0, false, false, false); }
        $pdf->SetDrawColor(255, 255, 255); $pdf->Line(5.5, 51, 31.5, 51);
        $pdf->SetFont('helvetica', '', 5.2);
        $pdf->Text(5.5, 52.2, trim(($Sc['sign_name'] !== '' ? $Sc['sign_name'] . ' | ' : '') . $Sc['sign_title'] . ' - ' . $Sc['library_name']));
    }
    if ($Sc['show_qr'] === '1') {
        $pdf->SetFillColor(255, 255, 255); $pdf->Rect($W - 21, 40, 16, 16, 'F');
        $pdf->write2DBarcode($code, 'QRCODE,M', $W - 20, 41, 14, 14, ['bgcolor' => [255, 255, 255], 'fgcolor' => [15, 23, 42]], 'N');
        $pdf->SetTextColor(15, 23, 42); $pdf->SetFont('courier', 'B', 4);
        $pdf->Text($W - 19.5, 56.6, $code);
    }
    $pdf->SetTextColor(255, 255, 255); $pdf->SetAlpha(0.72); $pdf->SetFont('helvetica', '', 4.6);
    $pdf->Text(5.5, $H - 3, $Sc['footer_text']); $pdf->SetAlpha(1);

    /* ---- SISI BELAKANG ---- */
    $pdf->AddPage();
    $pdf->LinearGradient(0, 0, $W, $H, $c1, $c2, ['x1' => 0, 'y1' => 0, 'x2' => 1, 'y2' => 1]);
    $pdf->SetFillColor(11, 18, 32); $pdf->Rect(0, 0, $W, 7, 'F');
    $pdf->SetFillColor($ca[0], $ca[1], $ca[2]); $pdf->Rect(0, 7, $W, 0.6, 'F');
    $pdf->SetFillColor(255, 255, 255); $pdf->Rect(5.5, 11, 42, 13, 'F');
    if ($signPath) { $pdf->Image($signPath, 19, 12, 15, 8, '', '', '', true, 300, '', false, false, 0, false, false, false); }
    $pdf->SetTextColor(100, 116, 139); $pdf->SetFont('helvetica', '', 4.6);
    $pdf->Text(11, 22.3, 'TANDA TANGAN ' . mb_strtoupper($Sc['sign_title']));
    $pdf->SetFillColor($ca[0], $ca[1], $ca[2]);
    $pdf->RoundedRect($W - 29, 11, 24, 4.5, 1.2, '1111', 'F');
    $pdf->SetTextColor(30, 41, 59); $pdf->SetFont('helvetica', 'B', 5);
    $pdf->Text($W - 27, 12.6, 'BERLAKU S/D');
    $pdf->SetTextColor(255, 255, 255); $pdf->SetFont('helvetica', 'B', 10.5);
    $pdf->Text($W - 29, 18.5, $valid);
    $pdf->SetFont('helvetica', '', 4.8); $pdf->SetAlpha(0.85);
    $pdf->Text($W - 29, 23.5, 'Diterbitkan: ' . date('d/m/Y') . '  |  ID ' . $id);
    $pdf->SetAlpha(1);
    $pdf->SetTextColor($ca[0], $ca[1], $ca[2]); $pdf->SetFont('helvetica', 'B', 5);
    $pdf->Text(5.5, 28, 'KETENTUAN PENGGUNAAN KARTU');
    $pdf->SetTextColor(255, 255, 255); $pdf->SetFont('helvetica', '', 4.8);
    $terms = array_slice(preg_split('/\r?\n/', (string)($Sc['card_terms'] ?? ''), -1, PREG_SPLIT_NO_EMPTY), 0, 6);
    $y = 31;
    foreach ($terms as $i => $t) { $pdf->Text(6.5, $y, ($i + 1) . '. ' . $t); $y += 2.8; }
    if ($logoPath) { $pdf->Image($logoPath, 5.5, $H - 9, 5.5, 5.5, '', '', '', true, 300, '', false, false, 0, false, false, false); }
    $pdf->SetFont('helvetica', 'B', 6);
    $pdf->Text(13, $H - 7.2, mb_strtoupper($Sc['library_name']));
    $pdf->SetFont('courier', 'B', 5.5);
    $pdf->Text($W - 26, $H - 7.2, $code);
}

$pdf->Output('kartu-anggota-' . date('Ymd-His') . '.pdf', 'I');