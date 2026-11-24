<?php
/**
 * Plugin Name: Awesome Member Card
 * Plugin URI: https://slims.web.id
 * Description: Plugin kartu anggota modern dan elegan untuk SLiMS 9.6 ke atas, dengan fitur ulang tahun anggota.
 * Version: 1.5.3
 * Author: Yolis Libman
 * Author URI: -
 */

defined('INDEX_AUTH') OR die('Direct access not allowed!');

use SLiMS\Plugins;

// ==== Pendaftaran menu modul Keanggotaan ====
if (method_exists(Plugins::getInstance(), 'registerMenu')) {
    Plugins::getInstance()->registerMenu('membership', 'Kartu Anggota Keren', __DIR__ . '/card_page.php', 'Dashboard kartu anggota');
    Plugins::getInstance()->registerMenu('membership', 'Ulang Tahun Anggota', __DIR__ . '/birthday_page.php', 'Daftar anggota yang berulang tahun');
    Plugins::getInstance()->registerMenu('membership', 'Pengaturan Kartu', __DIR__ . '/settings_page.php', 'Pengaturan desain kartu');
    Plugins::getInstance()->registerMenu('membership', 'Produksi & Pencetakan', __DIR__ . '/batch_page.php', 'Cetak massal, lembar A4, dan ekspor PDF');
}

// ==== Route OPAC: halaman ucapan ulang tahun publik ====
if (!function_exists('amc_opac_wish')) {
    function amc_opac_wish() {
        if (isset($_GET['p']) && $_GET['p'] === 'amc-birthday-wish') {
            require_once __DIR__ . '/amc_lib.php';
            require __DIR__ . '/birthday_wish.php';
            exit;
        }
    }
}
amc_opac_wish();
if (function_exists('add_action')) {
    add_action('content_before_load', 'amc_opac_wish');
}

// ==== Intersepsi awal: proxy foto, ekspor PDF, email ulang tahun ====
if (!function_exists('amc_intercept')) {
    function amc_intercept() {
        $act = $_GET['act'] ?? ($_GET['p'] ?? '');
        if ($act !== 'awesome_member_card') { return; }
        $action = $_GET['action'] ?? '';

        if ($action === 'photo') {
            require_once __DIR__ . '/amc_lib.php';
            require __DIR__ . '/photo.php';
            exit;
        }
        if ($action === 'batch_pdf') {
            require_once __DIR__ . '/amc_lib.php';
            require_once __DIR__ . '/inc_card_render.php';
            require __DIR__ . '/batch_pdf.php';
            exit;
        }
        if ($action === 'birthday_email') {
            require_once __DIR__ . '/amc_lib.php';
            require __DIR__ . '/birthday_email.php';
            exit;
        }
    }
}
amc_intercept();

// ==== Penanganan halaman & aksi plugin ====
if (!function_exists('amc_dispatch')) {
    function amc_dispatch() {
        $act = $_GET['act'] ?? ($_GET['p'] ?? '');
        if ($act !== 'awesome_member_card') { return; }
        require_once __DIR__ . '/migration.php';
        run_awesome_card_migration();
        $action = $_GET['action'] ?? '';
        if ($action === 'print') { require __DIR__ . '/print_card.php'; exit; }
        if ($action === 'settings_save' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { require __DIR__ . '/settings_save.php'; exit; }
        if ($action === 'batch_save' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { require __DIR__ . '/batch_save.php'; exit; }
        if ($action === 'batch_print') { require __DIR__ . '/batch_print.php'; exit; }
        require __DIR__ . '/index.php';
        exit;
    }
}

// Daftarkan hook routing untuk lingkungan yang mendukungnya
Plugins::getInstance()->register('routing', function () { amc_dispatch(); });

// WAJIB: panggil langsung agar aksi plugin tertangani seketika
amc_dispatch();