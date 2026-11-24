<?php
defined('INDEX_AUTH') OR die('Direct access not allowed!');

if (!function_exists('run_awesome_card_migration')) {
    function run_awesome_card_migration() {
        global $dbs;
        $table = 'plugin_awesome_card_settings';
        
        // 1. Buat tabel jika belum ada (Menggantikan SHOW TABLES untuk efisiensi)
        $create_sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `setting_name` VARCHAR(50) NOT NULL,
            `setting_value` TEXT,
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_name` (`setting_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        try {
            $dbs->query($create_sql);
        } catch (\Throwable $e) {
            // Jika gagal membuat tabel (misal: masalah hak akses database), hentikan migrasi
            return;
        }

        // 2. Definisikan nilai default + penanda versi
        $defaults = [
            'library_name'   => 'Perpustakaan Digital',
            'card_subtitle'  => 'Library Member Card',
            'primary_color'  => '#1e3a8a',
            'secondary_color'=> '#3b82f6',
            'accent_color'   => '#fbbf24',
            'card_style'     => 'aurora',
            'card_font'      => 'modern',
            'show_photo'     => '1',
            'show_qr'        => '1',
            'show_signature' => '1',
            'show_holo'      => '1',
            'show_chip'      => '1',
            'valid_years'    => '2',
            'footer_text'    => 'Kartu ini adalah properti resmi perpustakaan.',
            'card_terms'     => "Kartu ini merupakan identitas resmi anggota perpustakaan.\nWajib ditunjukkan saat peminjaman dan pengembalian koleksi.\nKartu tidak dapat dipindahtangankan kepada orang lain.\nKehilangan kartu harap segera dilaporkan kepada petugas sirkulasi.\nMasa berlaku kartu tertera pada bagian belakang ini.",
            'sign_title'     => 'Kepala Perpustakaan',
            'sign_name'      => '',
            'logo_file'      => '',
            'sign_file'      => '',
            'plugin_version' => '1.4.7' // Penanda versi untuk migrasi di masa depan
        ];

        // 3. Cek cepat: Apakah inisialisasi sudah pernah dilakukan?
        // Kita cek keberadaan 'plugin_version'. Jika ada, asumsikan data default sudah terisi.
        $check = $dbs->query("SELECT 1 FROM `{$table}` WHERE `setting_name` = 'plugin_version' LIMIT 1");
        if ($check && $check->num_rows > 0) {
            return; // Sudah terinisialisasi, hentikan eksekusi untuk menghemat resource server
        }

        // 4. Masukkan semua default sekaligus menggunakan INSERT IGNORE
        // INSERT IGNORE akan otomatis melewatkan baris yang sudah ada (karena UNIQUE KEY)
        $values = [];
        foreach ($defaults as $name => $value) {
            $escaped_name = $dbs->real_escape_string($name);
            $escaped_value = $dbs->real_escape_string($value);
            $values[] = "('" . $escaped_name . "', '" . $escaped_value . "')";
        }
        
        $sql = "INSERT IGNORE INTO `{$table}` (`setting_name`, `setting_value`) VALUES " . implode(',', $values);
        
        try {
            $dbs->query($sql);
        } catch (\Throwable $e) {
            // Abaikan error jika terjadi duplikasi atau masalah minor lainnya
        }
    }
}