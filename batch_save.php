<?php
defined('INDEX_AUTH') OR die('Direct access not allowed!');
require_once __DIR__ . '/amc_lib.php';

$tpl = [];
foreach ((array)($_POST['tpl'] ?? []) as $tid => $cfg) {
    $tid = (string)(int)$tid;
    if ($tid === '0') continue;
    $hex = function ($v) { return preg_match('/^#[0-9a-fA-F]{6}$/', (string)$v) ? $v : ''; };
    $entry = array_filter([
        'primary_color'   => $hex($cfg['p'] ?? ''),
        'secondary_color' => $hex($cfg['s'] ?? ''),
        'accent_color'    => $hex($cfg['a'] ?? ''),
        'card_style'      => in_array($cfg['style'] ?? '', ['aurora','glass','dark'], true) ? $cfg['style'] : '',
    ], function ($v) { return $v !== ''; });
    if ($entry) $tpl[$tid] = $entry;
}
amc_save_setting('type_templates', json_encode($tpl));

$back = 'index.php?mod=membership&act=awesome_member_card&action=batch_done';
header('Location: ' . amc_container_url(__DIR__ . '/batch_page.php') . '&saved=1');
exit;