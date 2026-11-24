<?php
/**
 * Awesome Member Card — Pengirim ucapan ulang tahun (final 1.6.0)
 * Perbaikan: URL absolut, subjek MIME, escape HTML, CSRF, header From aman,
 *            rantai SMTP->mail(), log anti-kirim-ganda.
 */
defined('INDEX_AUTH') OR die('Direct access not allowed!');
require_once __DIR__ . '/amc_lib.php';

global $dbs;
while (ob_get_level() > 0) { @ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (!function_exists('amc_be_json')) {
    function amc_be_json($arr) { echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
}

/* ==== 1. SESI STAF (tanah aman: TIDAK pernah session_start sendiri) ==== */
if (session_status() !== PHP_SESSION_ACTIVE) {
    amc_be_json(['success' => false, 'error' => 'Sesi belum siap. Muat ulang halaman lalu coba lagi.']);
}
if ((int)($_SESSION['uid'] ?? 0) <= 0) {
    amc_be_json(['success' => false, 'error' => 'Sesi tidak valid. Silakan login ulang.']);
}
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    amc_be_json(['success' => false, 'error' => 'Metode tidak diizinkan.']);
}

/* ==== 2. CSRF ==== */
$token = (string)($_POST['csrf_token'] ?? '');
if ($token === '' || empty($_SESSION['amc_csrf_token']) || !hash_equals((string)$_SESSION['amc_csrf_token'], $token)) {
    amc_be_json(['success' => false, 'error' => 'Token keamanan tidak valid. Muat ulang halaman.']);
}

$member_id = (int)($_POST['member_id'] ?? 0);
$force     = ((string)($_POST['force'] ?? '') === '1');
if ($member_id <= 0) amc_be_json(['success' => false, 'error' => 'ID anggota tidak valid.']);

/* ==== 3. LOG PENGIRIMAN (anti kirim ganda per anggota per tahun) ==== */
if (!function_exists('amc_be_log_ready')) {
    function amc_be_log_ready() {
        global $dbs;
        static $ready = null;
        if ($ready !== null) return $ready;
        $ready = false;
        try {
            $dbs->query("CREATE TABLE IF NOT EXISTS `plugin_amc_birthday_log` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `member_id` INT(11) NOT NULL,
                `year` SMALLINT(4) NOT NULL,
                `channel` VARCHAR(20) NOT NULL DEFAULT '',
                `status` VARCHAR(10) NOT NULL DEFAULT '',
                `uid` INT(11) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_member_year` (`member_id`,`year`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $ready = true;
        } catch (\Throwable $e) { $ready = false; }
        return $ready;
    }
}
$logOk  = amc_be_log_ready();
$year   = (int)date('Y');
$uidNow = (int)($_SESSION['uid'] ?? 0);

if ($logOk && !$force) {
    try {
        $st = $dbs->prepare("SELECT id FROM plugin_amc_birthday_log WHERE member_id = ? AND year = ? AND status = 'sent' LIMIT 1");
        $st->bind_param('ii', $member_id, $year);
        $st->execute();
        $exists = $st->get_result()->fetch_assoc();
        $st->close();
        if ($exists) {
            amc_be_json(['success' => false, 'duplicate' => true,
                'error' => 'Ucapan tahun ' . $year . ' sudah pernah dikirim ke anggota ini. Gunakan tombol "Kirim Ulang" bila memang perlu.']);
        }
    } catch (\Throwable $e) { /* log tidak menghalangi pengiriman */ }
}

try {
    /* ==== 4. DATA ANGGOTA ==== */
    $st = $dbs->prepare("SELECT member_name, member_email, birth_date FROM member WHERE member_id = ? LIMIT 1");
    $st->bind_param('i', $member_id);
    $st->execute();
    $member = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$member) amc_be_json(['success' => false, 'error' => 'Data anggota tidak ditemukan.']);
    $email = trim((string)($member['member_email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        amc_be_json(['success' => false, 'error' => 'Email anggota kosong atau tidak valid.']);
    }

    $S           = amc_settings();
    $name        = trim((string)($member['member_name'] ?? 'Anggota'));
    $libraryName = trim((string)($S['library_name'] ?? 'Perpustakaan'));
    $subtitle    = trim((string)($S['card_subtitle'] ?? ''));

    /* ==== 5. TAUTAN ABSOLUT (kunci perbaikan tombol di email) ==== */
    $base = function_exists('amc_site_base') ? amc_site_base() : ((defined('SWB') ? SWB : '/'));
    if (!preg_match('#^https?://#i', $base)) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
        $host  = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $base  = ($https ? 'https://' : 'http://') . $host . '/' . ltrim((string)$base, '/');
    }
    $base    = rtrim($base, '/') . '/';
    $wishUrl = $base . 'index.php?p=amc-birthday-wish&name=' . rawurlencode(mb_substr($name, 0, 80));

    /* ==== 6. ESCAPE SEMUA DATA DINAMIS ==== */
    $hName  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $hLib   = htmlspecialchars($libraryName, ENT_QUOTES, 'UTF-8');
    $hSub   = htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8');
    $hUrl   = htmlspecialchars($wishUrl, ENT_QUOTES, 'UTF-8');
    $hUrlTx = htmlspecialchars($wishUrl, ENT_QUOTES, 'UTF-8');

    $subjectRaw = 'Selamat Ulang Tahun, ' . $name . '! 🎂';
    $subject    = '=?UTF-8?B?' . base64_encode($subjectRaw) . '?=';

    $body = '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">'
          . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
          . '<body style="margin:0;padding:0;background:#f1f5f9;">'
          . '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">'
          . '<div style="background:#ec4899;background-image:linear-gradient(135deg,#ec4899,#f59e0b);padding:30px;border-radius:12px;text-align:center;color:#ffffff;">'
          . '<h1 style="margin:0;font-size:28px;color:#ffffff;">🎉 Selamat Ulang Tahun! 🎉</h1>'
          . '<h2 style="margin:10px 0 0;font-size:22px;color:#ffffff;">' . $hName . '</h2>'
          . '</div>'
          . '<div style="background:#ffffff;padding:28px;border-radius:12px;margin-top:18px;border:1px solid #e2e8f0;">'
          . '<p style="font-size:15px;line-height:1.8;color:#334155;margin:0 0 14px;">Di hari yang spesial ini, kami dari <strong>' . $hLib . '</strong> mengucapkan:</p>'
          . '<p style="font-size:18px;font-weight:bold;color:#ec4899;margin:0 0 14px;">Selamat Ulang Tahun! 🎊</p>'
          . '<p style="font-size:15px;line-height:1.8;color:#334155;margin:0 0 12px;">Semoga panjang umur, sehat selalu, dan diberikan keberkahan dalam setiap langkah.</p>'
          . '<p style="font-size:15px;line-height:1.8;color:#334155;margin:0 0 12px;">Semoga segala cita-cita dan harapan tercapai, serta senantiasa dalam lindungan-Nya.</p>'
          . '<p style="font-size:15px;line-height:1.8;color:#334155;margin:0 0 22px;">Terima kasih telah menjadi bagian dari keluarga besar perpustakaan kami! 📚✨</p>'
          . '<div style="text-align:center;margin:26px 0;">'
          . '<a href="' . $hUrl . '" style="display:inline-block;background:#ec4899;background-image:linear-gradient(135deg,#ec4899,#f59e0b);color:#ffffff;padding:13px 30px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:15px;">🎂 Lihat Ucapan Spesial</a>'
          . '</div>'
          . '<p style="font-size:12px;line-height:1.7;color:#64748b;margin:0 0 18px;">Bila tombol tidak berfungsi, salin tautan ini ke peramban:<br>'
          . '<a href="' . $hUrl . '" style="color:#3b82f6;word-break:break-all;">' . $hUrlTx . '</a></p>'
          . '<p style="font-size:13px;color:#64748b;margin:0;border-top:1px solid #e2e8f0;padding-top:18px;">Dengan hormat,<br><strong>' . $hLib . '</strong>'
          . ($hSub !== '' ? '<br>' . $hSub : '') . '</p>'
          . '</div></div></body></html>';

    /* ==== 7. HEADER AMAN (tanpa CRLF injection, domain bersih) ==== */
    $fromName = trim(preg_replace('/[\r\n"<>@\\\\]/u', '', $libraryName));
    if ($fromName === '') $fromName = 'Perpustakaan';
    $hostOnly = strtolower(preg_replace('/[^A-Za-z0-9.\-]/', '', explode(':', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'))[0]));
    if ($hostOnly === '' || strpos($hostOnly, '.') === false) $hostOnly = 'localhost.localdomain';
    $fromAddr = 'noreply@' . $hostOnly;
    $cfgFrom  = trim((string)($S['mail_from'] ?? ''));
    if ($cfgFrom !== '' && filter_var($cfgFrom, FILTER_VALIDATE_EMAIL)) {
        $fromAddr = $cfgFrom;
        $hostOnly = substr(strrchr($cfgFrom, '@'), 1);
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromAddr . '>',
        'Message-ID: <amc-' . bin2hex(random_bytes(10)) . '@' . $hostOnly . '>',
        'X-Mailer: AwesomeMemberCard/1.6.0',
        'Auto-Submitted: auto-generated',
    ];
    $bodyEncoded = chunk_split(base64_encode($body), 76, "\r\n");

    /* ==== 8. RANTAI PENGIRIMAN: SMTP SLiMS dulu, lalu mail() ==== */
    $sent = false; $via = ''; $err = '';

    if (class_exists('\SLiMS\Mail')) {
        try {
            $m = new \SLiMS\Mail();
            if (method_exists($m, 'to') && method_exists($m, 'subject') && method_exists($m, 'body') && method_exists($m, 'send')) {
                if (method_exists($m, 'from'))    { try { $m->from($fromAddr, $fromName); } catch (\Throwable $e) {} }
                if (method_exists($m, 'isHTML'))  { try { $m->isHTML(true); } catch (\Throwable $e) {} }
                elseif (method_exists($m, 'html')){ try { $m->html(true); } catch (\Throwable $e) {} }
                $m->to($email);
                $m->subject($subjectRaw);   // kelas SMTP umumnya meng-encode sendiri
                $m->body($body);
                $r = $m->send();
                if ($r === null || $r === true || (is_int($r) && $r > 0) || $r === 1) { $sent = true; $via = 'smtp'; }
            }
        } catch (\Throwable $e) { $err = $e->getMessage(); }
    }

    if (!$sent) {
        $ok = @mail($email, $subject, $bodyEncoded, implode("\r\n", $headers));
        if ($ok) { $sent = true; $via = 'mail'; }
    }

    /* ==== 9. CATAT LOG ==== */
    if ($logOk) {
        try {
            $status  = $sent ? 'sent' : 'fail';
            $channel = $via !== '' ? $via : 'none';
            $now     = date('Y-m-d H:i:s');
            $st = $dbs->prepare("INSERT INTO plugin_amc_birthday_log (member_id, year, channel, status, uid, created_at) VALUES (?,?,?,?,?,?)");
            $st->bind_param('iissis', $member_id, $year, $channel, $status, $uidNow, $now);
            $st->execute();
            $st->close();
        } catch (\Throwable $e) { /* abaikan */ }
    }

    if ($sent) {
        amc_be_json(['success' => true, 'via' => $via, 'email' => $email]);
    }

    $hint = 'Server belum dapat mengirim email. Di Laragon/Windows, fungsi mail() perlu SMTP aktif — '
          . 'atur SMTP SLiMS (Sistem → Konfigurasi) atau gunakan tombol Copy WA sebagai alternatif.';
    amc_be_json(['success' => false, 'error' => $err !== '' ? ('Gagal mengirim: ' . $err) : $hint]);

} catch (\Throwable $e) {
    error_log('[AMC Birthday Email] ' . $e->getMessage());
    amc_be_json(['success' => false, 'error' => 'Terjadi kesalahan sistem. Detail dicatat di log server.']);
}