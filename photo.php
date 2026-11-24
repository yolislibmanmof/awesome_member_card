<?php
defined('INDEX_AUTH') OR die('Direct access not allowed!');

require_once __DIR__ . '/amc_lib.php';
global $dbs;

// ============================================================
// 1. VALIDASI ID ANGGOTA (tanpa wajib sesi)
// ============================================================
$requested_id = $_GET['member_id'] ?? null;
if (!is_string($requested_id) && !is_int($requested_id)) {
    http_response_code(404);
    exit;
}
$id = trim((string)$requested_id);
if ($id === '' || strlen($id) > 64 || preg_match('/[\x00-\x1F\x7F]/', $id)) {
    http_response_code(404);
    exit;
}

// ============================================================
// 2. OPSIONAL: validasi sesi (bila diinginkan lebih ketat)
// ============================================================
// Nonaktifkan blok ini jika ingin foto terbuka publik
// (direkomendasikan untuk plugin kartu anggota).
/*
if (session_status() === PHP_SESSION_NONE) session_start();
$uid = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
$mid = isset($_SESSION['mid']) ? trim((string)$_SESSION['mid']) : '';
$is_staff = $uid > 0;
$is_member = ($mid !== '') && (is_callable(['utility','isMemberLogin']) ? \utility::isMemberLogin() : true);
if (!$is_staff && !$is_member) { http_response_code(403); exit; }
if ($is_member && !$is_staff && !hash_equals($mid, $id)) { http_response_code(403); exit; }
*/

// ============================================================
// 3. AMBIL DATA FOTO DARI DATABASE
// ============================================================
$row = null;
try {
    if (!isset($dbs) || !is_object($dbs)) {
        throw new \RuntimeException('Koneksi database tidak tersedia.');
    }
    $st = $dbs->prepare('SELECT member_image FROM member WHERE member_id = ? LIMIT 1');
    if (!$st) throw new \RuntimeException('Gagal menyiapkan query.');
    $st->bind_param('s', $id);
    if (!$st->execute()) throw new \RuntimeException('Query gagal: ' . $st->error);
    $member_image = null;
    $st->bind_result($member_image);
    if ($st->fetch()) $row = ['member_image' => $member_image];
    $st->close();
} catch (\Throwable $e) {
    error_log('[AMC Photo] Kesalahan database: ' . $e->getMessage());
    http_response_code(500);
    exit;
}
if (!$row) { http_response_code(404); exit; }

// ============================================================
// 4. VALIDASI NAMA DAN LOKASI FOTO
// ============================================================
$raw = trim((string)amc_photo_clean($row['member_image'] ?? ''));
if ($raw === '' || preg_match('#^(?:https?:)?//#i', $raw) || preg_match('/^[a-z][a-z0-9+.-]*:/i', $raw)) {
    http_response_code(404);
    exit;
}
try {
    $path = amc_member_photo_path($row);
} catch (\Throwable $e) {
    error_log('[AMC Photo] Gagal menentukan lokasi: ' . $e->getMessage());
    http_response_code(500);
    exit;
}
if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

// ============================================================
// 5. VALIDASI FORMAT FOTO (toleran terhadap ekstensi yang tidak cocok)
// ============================================================
$image_info = @getimagesize($path);
if ($image_info === false) { http_response_code(404); exit; }
$actual_mime = $image_info['mime'] ?? '';
$valid_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($actual_mime, $valid_mimes, true)) { http_response_code(404); exit; }

// ============================================================
// 6. BUKA FILE
// ============================================================
$handle = @fopen($path, 'rb');
if ($handle === false) { http_response_code(404); exit; }
$size = @filesize($path);

// ============================================================
// 7. KIRIM RESPONS FOTO (dengan cache publik agar ringan)
// ============================================================
header('Content-Type: ' . $actual_mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=604800');
if ($size !== false) header('Content-Length: ' . $size);

while (!feof($handle)) {
    $chunk = fread($handle, 8192);
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
}
fclose($handle);
exit;