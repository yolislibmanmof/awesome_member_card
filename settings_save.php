<?php
defined('INDEX_AUTH') OR die('Direct access not allowed!');
require_once __DIR__ . '/amc_lib.php';

// === 1. VALIDASI CSRF (WAJIB) ===
// Sesi dijamin aktif oleh router (amc_handle); jangan session_start() sendiri.
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (($_GET['ajax'] ?? '') === '1') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'err' => 'Sesi admin belum siap. Muat ulang halaman lalu coba lagi.']);
        exit;
    }
    die('Akses ditolak: sesi admin belum siap. Muat ulang halaman lalu coba lagi.');
}
if (!isset($_POST['csrf_token']) || !isset($_SESSION['amc_csrf_token']) || !hash_equals($_SESSION['amc_csrf_token'], $_POST['csrf_token'])) {
    if (($_GET['ajax'] ?? '') === '1') {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'err' => 'Token keamanan tidak valid. Silakan muat ulang halaman dan coba lagi.']);
        exit;
    }
    die('Akses ditolak: Token keamanan (CSRF) tidak valid.');
}

$color = function ($v, $old) { return preg_match('/^#[0-9a-fA-F]{6}$/', (string)$v) ? $v : $old; };
$old = amc_settings();

// === 2. SIMPAN PENGATURAN TEKS & PILIHAN ===
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

// === 3. PENANGANAN UNGGAHAN FILE & PENGHAPUSAN ===
$errs = [];

// Helper untuk menghapus file lama dari disk (mencegah file yatim/orphaned files)
$delete_old_file = function($db_key) use ($old) {
    $old_file = $old[$db_key] ?? '';
    if ($old_file !== '') {
        $old_path = __DIR__ . '/assets/' . basename($old_file);
        if (is_file($old_path)) {
            @unlink($old_path);
        }
    }
};

$handleUpload = function ($key, $prefix, $db_key) use (&$errs, $old, $delete_old_file) {
    if (empty($_FILES[$key]['name'])) return null;
    if ($_FILES[$key]['error'] !== UPLOAD_ERR_OK) { 
        $errs[] = $key . '_upload_error_' . $_FILES[$key]['error']; 
        return ''; 
    }
    
    $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png','jpg','jpeg','gif','webp'], true)) {
        $errs[] = $key . '_format_tidak_valid';
        return '';
    }
    
    if ($_FILES[$key]['size'] > 1048576) {
        $errs[] = $key . '_ukuran_terlalu_besar';
        return '';
    }

    // Validasi konten asli file (mencegah script berbahaya yang disembunyikan sebagai gambar)
    $img_info = @getimagesize($_FILES[$key]['tmp_name']);
    if ($img_info === false) {
        $errs[] = $key . '_bukan_gambar_valid';
        return '';
    }

    if (!is_dir(__DIR__ . '/assets')) { 
        @mkdir(__DIR__ . '/assets', 0775, true); 
    }
    
    // Gunakan random_bytes untuk nama file yang aman dan tidak bisa ditebak
    $fname = $prefix . bin2hex(random_bytes(8)) . '.' . $ext; 
    $dest = __DIR__ . '/assets/' . $fname;
    
    if (move_uploaded_file($_FILES[$key]['tmp_name'], $dest)) {
        // Hapus file lama dari server jika upload baru berhasil
        $delete_old_file($db_key);
        return $fname;
    }
    
    $errs[] = $key . '_gagal_disimpan';
    return '';
};

$logo = $handleUpload('logo', 'logo_', 'logo_file');
if ($logo !== null) {
    amc_save_setting('logo_file', $logo);
} elseif (isset($_POST['logo_remove'])) {
    $delete_old_file('logo_file');
    amc_save_setting('logo_file', '');
}

$sign = $handleUpload('sign', 'sign_', 'sign_file');
if ($sign !== null) {
    amc_save_setting('sign_file', $sign);
} elseif (isset($_POST['sign_remove'])) {
    $delete_old_file('sign_file');
    amc_save_setting('sign_file', '');
}

// === 4. RESPONS AJAX ATAU REDIRECT ===
if (($_GET['ajax'] ?? '') === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'ok'  => empty($errs), 
        'err' => implode('; ', $errs)
    ]);
    exit;
}

$back = amc_container_url(__DIR__ . '/settings_page.php') . '&saved=1' . ($errs ? '&err=' . urlencode(implode('; ', $errs)) : '');
header('Location: ' . $back);
exit;