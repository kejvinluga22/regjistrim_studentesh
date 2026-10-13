<?php
// Sherben foton, foton e vogel (&thumb=1) ose dokumentin Word te nje studenti, nga cilado dosje ruajtjeje
require_once __DIR__ . '/lib.php';
require_api();

$s = student_record($_GET['s'] ?? 0);
$types = [
    'jpg'  => 'image/jpeg',
    'png'  => 'image/png',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

$path = '';
if ($s && isset($_GET['thumb'])) {
    $path = thumb_path($s['id']);
    $name = basename($path);
} elseif ($s) {
    $name = basename($_GET['f'] ?? '');
    // lejohen vetem skedaret e ketij studenti
    if (in_array($name, [$s['photo'], $s['docx']], true)) $path = student_files_dir($s) . '/' . $name;
}
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

if ($path === '' || !isset($types[$ext]) || !is_file($path)) {
    http_response_code(404);
    exit('Skedari nuk u gjet.');
}

// Shfletuesi e mban foton ne memorie dhe e shkarkon perseri vetem nese ka ndryshuar
$mtime = filemtime($path);
header('Cache-Control: no-cache');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
$since = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');
if ($since !== false && $since >= $mtime) {
    http_response_code(304);
    exit;
}

header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . filesize($path));
if ($ext === 'docx') {
    $ascii = to_ascii(pathinfo($name, PATHINFO_FILENAME)) . '.docx';
    header("Content-Disposition: attachment; filename=\"$ascii\"; filename*=UTF-8''" . rawurlencode($name));
}
readfile($path);
