<?php
/**
 * Plugin Name: Awesome Member Card
 * Plugin URI: https://github.com/yolislibmanmof/sonic
 * Description: Kartu anggota modern untuk SLiMS 9.6 ke atas - dashboard kartu, pengaturan desain, produksi massal hemat kertas/A4/CR80/PDF, dan ucapan ulang tahun anggota.
 * Version: 1.6.0
 * Author: Yolis Libman
 * Author URI: https://slims.web.id
 */

defined('INDEX_AUTH') OR die('Direct access not allowed!');

use SLiMS\Plugins;

if (!defined('AMC_VERSION')) define('AMC_VERSION', '1.6.0');
if (!defined('AMC_ACT'))     define('AMC_ACT', 'awesome_member_card');

require_once __DIR__ . '/amc_lib.php';

/* ============================================================
 * 1. PENDAFTARAN MENU (sekali saja, anti duplikat)
 * ============================================================ */
if (!defined('AMC_MENUS_DONE')) {
    define('AMC_MENUS_DONE', 1);
    $amc_p = Plugins::getInstance();
    if (method_exists($amc_p, 'registerMenu')) {
        $amc_p->registerMenu('membership', 'Kartu Anggota Keren', __DIR__ . '/card_page.php', 'Dashboard kartu anggota');
        $amc_p->registerMenu('membership', 'Ulang Tahun Anggota', __DIR__ . '/birthday_page.php', 'Daftar anggota yang berulang tahun');
        $amc_p->registerMenu('membership', 'Pengaturan Kartu', __DIR__ . '/settings_page.php', 'Pengaturan desain kartu');
        $amc_p->registerMenu('membership', 'Produksi & Pencetakan', __DIR__ . '/batch_page.php', 'Cetak massal, lembar A4, dan ekspor PDF');
    }
    unset($amc_p);
}

/* ============================================================
 * 2. UTILITAS KEAMANAN & SUMBER DAYA
 * ============================================================ */
if (!function_exists('amc_session_ready')) {
    function amc_session_ready() {
        // PENTING: plugin dimuat dari sysconfig.inc.php SEBELUM sesi admin siap.
        // Kita TIDAK pernah memanggil session_start() sendiri agar tidak
        // merebut nama/handler sesi milik SLiMS (bisa merusak login).
        return session_status() === PHP_SESSION_ACTIVE;
    }
}

if (!function_exists('amc_is_staff')) {
    function amc_is_staff() {
        if (!amc_session_ready()) return false;
        if ((int)($_SESSION['uid'] ?? 0) > 0) return true;
        if (!empty($_SESSION['uname']) && isset($_SESSION['priv'])) return true;
        return false;
    }
}

if (!function_exists('amc_same_origin')) {
    function amc_same_origin() {
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') return true;
        $sources = [];
        if (!empty($_SERVER['HTTP_ORIGIN']))  $sources[] = (string)$_SERVER['HTTP_ORIGIN'];
        if (!empty($_SERVER['HTTP_REFERER'])) $sources[] = (string)$_SERVER['HTTP_REFERER'];
        if (!$sources) return true; // tanpa kedua header: rely pada CSRF token + sesi
        foreach ($sources as $u) {
            if ($u === 'null') continue;
            $h = strtolower((string)parse_url($u, PHP_URL_HOST));
            $p = parse_url($u, PHP_URL_PORT);
            if ($p) $h .= ':' . $p;
            if ($h !== '' && $h === $host) return true;
        }
        return false;
    }
}

if (!function_exists('amc_bytes')) {
    function amc_bytes($v) {
        $v = trim((string)$v);
        if ($v === '' || $v === '-1') return -1;
        $n = (float)$v;
        $u = strtolower(substr($v, -1));
        if ($u === 'g') $n *= 1073741824;
        elseif ($u === 'm') $n *= 1048576;
        elseif ($u === 'k') $n *= 1024;
        return (int)$n;
    }
}

if (!function_exists('amc_clean_output')) {
    function amc_clean_output() {
        while (ob_get_level() > 0) { @ob_end_clean(); }
        if (!headers_sent()) { @header_remove('Content-Type'); }
    }
}

if (!function_exists('amc_deny')) {
    function amc_deny($msg = 'Akses ditolak. Halaman ini hanya untuk pustakawan yang sudah login.') {
        amc_clean_output();
        http_response_code(403);
        $login = defined('AWB') ? AWB . 'index.php' : 'index.php';
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="id"><meta charset="utf-8">'
           . '<div style="font-family:Segoe UI,sans-serif;max-width:520px;margin:60px auto;padding:28px;'
           . 'border:1px solid #fca5a5;background:#fef2f2;color:#991b1b;border-radius:14px;line-height:1.7">'
           . '<h3 style="margin:0 0 10px">Awesome Member Card</h3>'
           . '<p style="margin:0 0 16px">' . htmlspecialchars($msg) . '</p>'
           . '<a href="' . htmlspecialchars($login) . '" style="display:inline-block;background:#1e3a8a;color:#fff;'
           . 'padding:10px 20px;border-radius:9px;text-decoration:none;font-weight:700">Masuk ke Admin</a></div></html>';
        exit;
    }
}

/* ============================================================
 * 3. ROUTE OPAC PUBLIK: halaman ucapan ulang tahun
 * ============================================================ */
if (!function_exists('amc_opac_wish')) {
    function amc_opac_wish() {
        if (($_GET['p'] ?? '') !== 'amc-birthday-wish') return false;
        $n = trim(strip_tags((string)($_GET['name'] ?? 'Anggota')));
        $n = function_exists('mb_substr') ? mb_substr($n, 0, 80) : substr($n, 0, 80);
        $_GET['name'] = ($n === '') ? 'Anggota' : $n;
        amc_clean_output();
        require __DIR__ . '/birthday_wish.php';
        exit;
    }
}

/* ============================================================
 * 4. ROUTER TUNGGAL SEMUA AKSI PLUGIN
 * ============================================================ */
if (!function_exists('amc_handle')) {
    function amc_handle() {
        if (($_GET['act'] ?? ($_GET['p'] ?? '')) !== AMC_ACT) return false;

        $action   = (string)($_GET['action'] ?? '');
        $method   = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $postOnly = ['settings_save', 'batch_save', 'birthday_email'];

        // Foto boleh diakses tanpa sesi HANYA dengan token HMAC yang sah
        $tokenOk = false;
        if ($action === 'photo' && isset($_GET['k'], $_GET['member_id'])) {
            $tokenOk = hash_equals(amc_id_token((int)$_GET['member_id']), (string)$_GET['k']);
        }

        if (!$tokenOk) {
            // Sesi belum siap (mis. dimuat dari sysconfig) -> tunda ke hook admin
            if (!amc_session_ready()) return false;
            if (!amc_is_staff()) amc_deny();
            if (in_array($action, $postOnly, true)) {
                if ($method !== 'POST')     amc_deny('Aksi ini hanya menerima metode POST.');
                if (!amc_same_origin())     amc_deny('Permintaan lintas situs ditolak (proteksi CSRF).');
            }
        }

        // Sumber daya untuk batch besar (sekaligus menetralkan bug downgrade di batch_pdf.php)
        if (in_array($action, ['batch_pdf', 'batch_print'], true)) {
            $lim = amc_bytes(ini_get('memory_limit'));
            if ($lim !== -1 && $lim < 268435456) @ini_set('memory_limit', '256M');
            @set_time_limit(300);
        }

        amc_ensure_table(); // migrasi mandiri, aman dipanggil dari jalur mana pun

        if (in_array($action, ['batch_pdf', 'batch_print', 'print'], true)) {
            require_once __DIR__ . '/inc_card_render.php';
        }

        $map = [
            'photo'          => 'photo.php',
            'batch_pdf'      => 'batch_pdf.php',
            'birthday_email' => 'birthday_email.php',
            'print'          => 'print_card.php',
            'batch_print'    => 'batch_print.php',
            'settings_save'  => 'settings_save.php',
            'batch_save'     => 'batch_save.php',
            'settings_page'  => 'settings_page.php',
            'batch_page'     => 'batch_page.php',
            'birthday_page'  => 'birthday_page.php',
            'card_page'      => 'card_page.php',
        ];
        $file = __DIR__ . '/' . ($map[$action] ?? 'card_page.php');
        if (!is_file($file)) $file = __DIR__ . '/card_page.php'; // fallback dashboard, bukan index.php

        amc_clean_output();
        require $file;
        exit;
    }
}

/* ==== Kompatibilitas nama lama (tidak menghapus API sebelumnya) ==== */
if (!function_exists('amc_intercept')) { function amc_intercept() { return amc_handle(); } }
if (!function_exists('amc_dispatch'))  { function amc_dispatch()  { return amc_handle(); } }

/* ============================================================
 * 5. EKSEKUSI: jalur cepat + jalur tertunda (setelah sesi admin siap)
 * ============================================================ */
amc_opac_wish();

if (($_GET['act'] ?? ($_GET['p'] ?? '')) === AMC_ACT) {
    if (!amc_handle()) {
        $amc_cb = function (...$args) { amc_handle(); };
        $amc_p  = Plugins::getInstance();
        $amc_p->register(Plugins::ADMIN_SESSION_AFTER_START, $amc_cb, 1);
        $amc_p->register(Plugins::ADMIN_AFTER_MODULE_LOADED, $amc_cb, 1);
        $amc_p->register('routing', $amc_cb, 1); // cadangan untuk lingkungan lain
        unset($amc_p, $amc_cb);
    }
}