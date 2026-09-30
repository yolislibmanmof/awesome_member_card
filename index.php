<?php
global $dbs;
require_once __DIR__ . '/amc_lib.php';
require_once __DIR__ . '/migration.php';
run_awesome_card_migration();
$S = amc_settings();

try {
    $members = $dbs->query("SELECT * FROM member ORDER BY member_id DESC LIMIT 60");
} catch (\Throwable $e) {
    $members = $dbs->query("SELECT * FROM member LIMIT 60");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kartu Anggota Keren</title>
<style>
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Segoe UI',sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;padding:30px;}
    .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;}
    .topbar h1{font-size:24px;background:linear-gradient(90deg,<?= $S['primary_color'] === '#1e3a8a' ? '#60a5fa' : $S['secondary_color'] ?>,<?= $S['accent_color'] ?>);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
    .btn{padding:8px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;}
    .back{background:#1e293b;color:#94a3b8;border:1px solid #334155;}
    .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;}
    .mini-card{background:linear-gradient(135deg,<?= $S['primary_color'] ?>,<?= $S['secondary_color'] ?>);border-radius:14px;padding:20px;display:flex;flex-direction:column;gap:14px;transition:transform .2s,box-shadow .2s;}
    .mini-card:hover{transform:translateY(-4px);box-shadow:0 12px 30px rgba(59,130,246,.35);}
    .mc-code{font-family:'Courier New',monospace;font-size:12px;letter-spacing:2px;opacity:.85;}
    .mc-name{display:block;font-size:17px;text-transform:uppercase;margin:4px 0;}
    .mc-mail{font-size:11px;opacity:.75;}
    .print{background:#fff;color:<?= $S['primary_color'] ?>;text-align:center;}
</style>
</head>
<body>
<header class="topbar">
    <h1>Kartu Anggota Keren</h1>
    <a class="btn back" href="index.php?mod=membership">&larr; Kembali ke Keanggotaan</a>
</header>
<main class="grid">
<?php if ($members && $members->num_rows > 0): ?>
    <?php while ($m = $members->fetch_assoc()):
        $id   = (int)($m['member_id'] ?? 0);
        $code = $m['member_code'] ?? ($m['member_card_id'] ?? ($m['member_number'] ?? (string)$id));
        $name = $m['member_name'] ?? ($m['member_full_name'] ?? 'Anggota');
        $mail = $m['member_email'] ?? ($m['email'] ?? '-');
    ?>
    <div class="mini-card">
        <div>
            <span class="mc-code"><?= htmlspecialchars((string)$code) ?></span>
            <strong class="mc-name"><?= htmlspecialchars((string)$name) ?></strong>
            <span class="mc-mail"><?= htmlspecialchars((string)$mail) ?></span>
        </div>
        <a class="btn print" target="_blank"
           href="index.php?mod=membership&act=awesome_member_card&action=print&member_id=<?= $id ?>">Cetak Kartu</a>
    </div>
    <?php endwhile; ?>
<?php else: ?>
    <p>Belum ada data anggota.</p>
<?php endif; ?>
</main>
</body>
</html>