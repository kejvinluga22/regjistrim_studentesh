<?php
// Hap dokumentin Word te studentit direkt ne Microsoft Word ne kete kompjuter (pa e shkarkuar)
require_once __DIR__ . '/lib.php';
require_api();
header('Content-Type: application/json; charset=utf-8');

if (!is_local_request()) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'Vetëm nga ky kompjuter.']));
}

$s = student_record($_GET['s'] ?? 0);
$path = $s ? realpath(student_files_dir($s) . '/' . $s['docx']) : false;
if (!$path || !is_file($path)) {
    http_response_code(404);
    exit(json_encode(['ok' => false, 'error' => 'Dokumenti Word nuk u gjet.']));
}

// "start" e hap skedarin me programin e Windows-it per .docx (Microsoft Word)
pclose(popen('start "" ' . escapeshellarg(str_replace('/', '\\', $path)), 'r'));
echo json_encode(['ok' => true]);
