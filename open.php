<?php
// Hap dosjen e studenteve ne Windows Explorer - vetem nga ky kompjuter.
// Me ?s=Emri Mbiemri hapet dosja me dokumentin Word te studentit te zgjedhur.
require_once __DIR__ . '/lib.php';
require_api();

if (!is_local_request()) {
    http_response_code(403);
    exit('Vetëm nga ky kompjuter.');
}

$dir = ensure_dir(students_dir()) ? realpath(students_dir()) : false;
if (!$dir) {
    http_response_code(404);
    exit('Dosja nuk u gjet.');
}
$dir = str_replace('/', '\\', $dir);

$arg = escapeshellarg($dir);
if (isset($_GET['s'])) {
    $s = student_record($_GET['s']);
    $docx = $s ? realpath(student_files_dir($s) . '/' . $s['docx']) : false;
    if ($docx) $arg = '/select,' . escapeshellarg(str_replace('/', '\\', $docx));
}
pclose(popen('start "" explorer.exe ' . $arg, 'r'));
http_response_code(204);
