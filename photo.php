<?php
defined('INDEX_AUTH') OR die('Direct access not allowed!');
require_once __DIR__ . '/amc_lib.php';

global $dbs;
$id = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
if ($id <= 0) { http_response_code(404); exit; }

try {
    $st = $dbs->prepare("SELECT member_image FROM member WHERE member_id = ?");
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
} catch (\Throwable $e) { $row = null; }
if (!$row) { http_response_code(404); exit; }

$raw = amc_photo_clean($row['member_image'] ?? '');
if ($raw === '' || preg_match('#^https?://#i', $raw)) { http_response_code(404); exit; }

$path = amc_member_photo_path($row);
if (!$path || !is_readable($path)) { http_response_code(404); exit; }

$ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'][$ext] ?? 'image/jpeg';
$size = @filesize($path);

header('Content-Type: ' . $mime);
if ($size) header('Content-Length: ' . $size);
header('Cache-Control: public, max-age=86400');
@readfile($path);
exit;