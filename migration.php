<?php
function run_awesome_card_migration() {
    global $dbs;
    $table = 'plugin_awesome_card_settings';
    $check = $dbs->query("SHOW TABLES LIKE '{$table}'");
    if ($check && $check->num_rows == 0) {
        $dbs->query("CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `setting_name` VARCHAR(50) NOT NULL,
            `setting_value` TEXT,
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_name` (`setting_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }
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
    ];
    foreach ($defaults as $name => $value) {
        $c = $dbs->prepare("SELECT id FROM `{$table}` WHERE setting_name = ?");
        $c->bind_param('s', $name);
        $c->execute();
        if ($c->get_result()->num_rows == 0) {
            $st = $dbs->prepare("INSERT INTO `{$table}` (`setting_name`,`setting_value`) VALUES (?,?)");
            $st->bind_param('ss', $name, $value);
            $st->execute();
        }
    }
}