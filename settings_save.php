<?php
defined('INDEX_AUTH') OR die('Direct access not allowed!');
require_once __DIR__ . '/amc_lib.php';

$color = function ($v, $old) { return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : $old; };
$old = amc_settings();

amc_save_setting('library_name',  trim($_POST['library_name']  ?? $old['library_name']));
amc_save_setting('card_subtitle', trim($_POST['card_subtitle'] ?? $old['card_subtitle']));
amc_save_setting('footer_text',   trim($_POST['footer_text']   ?? $old['footer_text']));
amc_save_setting('card_terms',    trim($_POST['card_terms']    ?? $old['card_terms']));
amc_save_setting('sign_title',    trim($_POST['sign_title']    ?? $old['sign_title']));
amc_save_setting('sign_name',     trim($_POST['sign_name']     ?? $old['sign_name']));
amc_save_setting('primary_color',   $color($_POST['primary_color']   ?? '', $old['primary_color']));
amc_save_setting('secondary_color', $color($_POST['secondary_color'] ?? '', $old['secondary_color']));
amc_save_setting('accent_color',    $color($_POST['accent_color']    ?? '', $old['accent_color']));
amc_save_setting('card_style',  in_array($_POST['card_style'] ?? '', ['aurora','glass','dark'], true) ? $_POST['card_style'] : $old['card_style']);
amc_save_setting('card_font',   in_array($_POST['card_font'] ?? '', ['modern','serif','mono'], true) ? $_POST['card_font'] : $old['card_font']);
amc_save_setting('valid_years', (string)max(1, min(10, (int)($_POST['valid_years'] ?? $old['valid_years']))));
amc_save_setting('show_photo',     isset($_POST['show_photo']) ? '1' : '0');
amc_save_setting('show_qr',        isset($_POST['show_qr']) ? '1' : '0');
amc_save_setting('show_signature', isset($_POST['show_signature']) ? '1' : '0');
amc_save_setting('show_holo',      isset($_POST['show_holo']) ? '1' : '0');
amc_save_setting('show_chip',      isset($_POST['show_chip']) ? '1' : '0');

$errs = [];
$handleUpload = function ($key, $prefix) use (&$errs) {
    if (empty($_FILES[$key]['name'])) return null;
    if ($_FILES[$key]['error'] !== UPLOAD_ERR_OK) { $errs[] = $key . '_upload_error_' . $_FILES[$key]['error']; return ''; }
    $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png','jpg','jpeg','gif','webp'], true) || $_FILES[$key]['size'] > 1048576) { $errs[] = $key . '_format_atau_ukuran_tidak_valid'; return ''; }
    if (!is_dir(__DIR__ . '/assets')) { @mkdir(__DIR__ . '/assets', 0775, true); }
    $fname = $prefix . time() . '.' . $ext;
    if (move_uploaded_file($_FILES[$key]['tmp_name'], __DIR__ . '/assets/' . $fname)) return $fname;
    $errs[] = $key . '_gagal_disimpan';
    return '';
};

$logo = $handleUpload('logo', 'logo_');
if ($logo !== null) amc_save_setting('logo_file', $logo);
if (isset($_POST['logo_remove'])) amc_save_setting('logo_file', '');

$sign = $handleUpload('sign', 'sign_');
if ($sign !== null) amc_save_setting('sign_file', $sign);
if (isset($_POST['sign_remove'])) amc_save_setting('sign_file', '');

if (($_GET['ajax'] ?? '') === '1') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'err' => implode('; ', $errs)]);
    exit;
}
$back = amc_container_url(__DIR__ . '/settings_page.php') . '&saved=1' . ($errs ? '&err=' . urlencode(implode('; ', $errs)) : '');
header('Location: ' . $back);
exit;