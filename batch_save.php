<?php
defined('INDEX_AUTH') OR die('Direct access not allowed!');
require_once __DIR__ . '/amc_lib.php';

// === VALIDASI CSRF ===
// Sesi dijamin aktif oleh router (amc_handle); jangan session_start() sendiri.
if (session_status() !== PHP_SESSION_ACTIVE) {
    die('Akses ditolak: sesi admin belum siap. Muat ulang halaman lalu coba lagi.');
}
if (!isset($_POST['csrf_token']) || !isset($_SESSION['amc_csrf_token']) || !hash_equals($_SESSION['amc_csrf_token'], $_POST['csrf_token'])) {
    die('Akses ditolak: Token keamanan tidak valid. Silakan kembali dan coba lagi.');
}

// === VALIDASI DAN SANITASI INPUT ===
$tpl = [];
foreach ((array)($_POST['tpl'] ?? []) as $tid => $cfg) {
    $tid = (string)(int)$tid;
    if ($tid === '0') continue;
    
    $hex = function ($v) { 
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string)$v) ? $v : ''; 
    };
    
    $entry = array_filter([
        'primary_color'   => $hex($cfg['p'] ?? ''),
        'secondary_color' => $hex($cfg['s'] ?? ''),
        'accent_color'    => $hex($cfg['a'] ?? ''),
        'card_style'      => in_array($cfg['style'] ?? '', ['aurora','glass','dark'], true) ? $cfg['style'] : '',
    ], function ($v) { return $v !== ''; });
    
    if ($entry) $tpl[$tid] = $entry;
}

// === SIMPAN KONFIGURASI ===
amc_save_setting('type_templates', json_encode($tpl, JSON_UNESCAPED_UNICODE));

// === REDIRECT KEMBALI ===
header('Location: index.php?mod=membership&act=awesome_member_card&action=batch_page&saved=1');
exit;