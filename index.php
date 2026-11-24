<?php
/**
 * Plugin Name: Awesome Member Card
 * Plugin URI: https://slims.web.id
 * Description: Plugin kartu anggota modern dan elegan untuk SLiMS 9.6 ke atas.
 * Version: 1.4.7
 * Author: Yolis Libman
 * Author URI: -
 */

defined('INDEX_AUTH') OR die('Direct access not allowed!');

use SLiMS\Plugins;

// 1. Registrasi Menu Navigasi
if (method_exists(Plugins::getInstance(), 'registerMenu')) {
    Plugins::getInstance()->registerMenu('membership', 'Kartu Anggota Keren', __DIR__ . '/card_page.php', 'Dashboard kartu anggota');
    Plugins::getInstance()->registerMenu('membership', 'Pengaturan Kartu', __DIR__ . '/settings_page.php', 'Pengaturan desain kartu');
    Plugins::getInstance()->registerMenu('membership', 'Produksi & Pencetakan', __DIR__ . '/batch_page.php', 'Cetak massal, lembar A4, dan ekspor PDF');
}

/* ==== 2. INTERSEPSI AWAL (KUNCI PERBAIKAN MASALAH ANDA) ==== 
   Fungsi ini dijalankan PALING AWAL, sebelum SLiMS Core memproses modul keanggotaan.
   Ini memastikan semua permintaan cetak/PDF/foto ditangani oleh plugin, bukan dialihkan. */
if (!function_exists('amc_intercept')) {
    function amc_intercept() {
        $act = $_GET['act'] ?? ($_GET['p'] ?? '');
        if ($act !== 'awesome_member_card') { return; }
        
        $action = $_GET['action'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // A. Proxy foto (respons cepat)
        if ($action === 'photo') {
            require_once __DIR__ . '/amc_lib.php';
            require __DIR__ . '/photo.php';
            exit;
        }

        // B. Ekspor PDF
        if ($action === 'batch_pdf') {
            require_once __DIR__ . '/amc_lib.php';
            require_once __DIR__ . '/inc_card_render.php';
            require __DIR__ . '/batch_pdf.php';
            exit;
        }

        // C. CETAK SATU KARTU (Ditambahkan agar tidak dialihkan)
        if ($action === 'print') {
            require_once __DIR__ . '/amc_lib.php';
            require_once __DIR__ . '/inc_card_render.php';
            require __DIR__ . '/print_card.php';
            exit;
        }

        // D. CETAK MASSAL / PRODUKSI (PERBAIKAN UTAMA: "Cetak Hemat")
        if ($action === 'batch_print') {
            require_once __DIR__ . '/amc_lib.php';
            require_once __DIR__ . '/inc_card_render.php';
            require __DIR__ . '/batch_print.php';
            exit;
        }

        // E. Penyimpanan Data (POST)
        if ($action === 'settings_save' && $method === 'POST') {
            require_once __DIR__ . '/amc_lib.php';
            require __DIR__ . '/settings_save.php';
            exit;
        }
        if ($action === 'batch_save' && $method === 'POST') {
            require_once __DIR__ . '/amc_lib.php';
            require __DIR__ . '/batch_save.php';
            exit;
        }
    }
}
// Jalankan intersepsi segera
amc_intercept();

/* ==== 3. PENANGANAN HALAMAN PLUGIN (ROUTING) ==== */
if (!function_exists('amc_dispatch')) {
    function amc_dispatch() {
        $act = $_GET['act'] ?? ($_GET['p'] ?? '');
        if ($act !== 'awesome_member_card') { return; }
        
        // Jalankan migrasi database jika diperlukan
        require_once __DIR__ . '/migration.php';
        run_awesome_card_migration();
        
        $action = $_GET['action'] ?? '';
        
        // Fallback ke halaman dashboard utama (card_page.php)
        require __DIR__ . '/card_page.php';
        exit;
    }
}

// Daftarkan hook routing ke SLiMS Core sebagai cadangan
Plugins::getInstance()->register('routing', function () { amc_dispatch(); });