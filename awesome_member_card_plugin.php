<?php
/**
 * Plugin Name: Awesome Member Card
 * Plugin URI: https://slims.web.id
 * Description: Plugin kartu anggota modern dan elegan untuk SLiMS 9.6 ke atas.
 * Version: 1.4.2
 * Author: Yolis Libman
 * Author URI: -
 */

defined('INDEX_AUTH') OR die('Direct access not allowed!');

use SLiMS\Plugins;

if (method_exists(Plugins::getInstance(), 'registerMenu')) {
    Plugins::getInstance()->registerMenu('membership', 'Kartu Anggota Keren', __DIR__ . '/card_page.php', 'Dashboard kartu anggota');
    Plugins::getInstance()->registerMenu('membership', 'Pengaturan Kartu', __DIR__ . '/settings_page.php', 'Pengaturan desain kartu');
    Plugins::getInstance()->registerMenu('membership', 'Produksi & Pencetakan', __DIR__ . '/batch_page.php', 'Cetak massal, lembar A4, dan ekspor PDF');
}

if (!function_exists('amc_dispatch')) {
    function amc_dispatch() {
        $act = $_GET['act'] ?? ($_GET['p'] ?? '');
        if ($act !== 'awesome_member_card') { return; }
        require_once __DIR__ . '/migration.php';
        run_awesome_card_migration();
        $action = $_GET['action'] ?? '';
        if ($action === 'print') { require __DIR__ . '/print_card.php'; exit; }
        if ($action === 'photo') { require __DIR__ . '/photo.php'; exit; }
        if ($action === 'settings_save' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { require __DIR__ . '/settings_save.php'; exit; }
        if ($action === 'batch_save' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { require __DIR__ . '/batch_save.php'; exit; }
        if ($action === 'batch_print') { require __DIR__ . '/batch_print.php'; exit; }
        if ($action === 'batch_pdf') { require __DIR__ . '/batch_pdf.php'; exit; }
        require __DIR__ . '/index.php';
        exit;
    }
}
Plugins::getInstance()->register('routing', function () { amc_dispatch(); });
amc_dispatch();