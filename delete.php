<?php
// Fshin nje student: Word, foto dhe te dhenat.
// Skedaret nuk zhduken pergjithmone - zhvendosen ne data/fshire/ (bashke me te dhenat ne student.json)
// qe te mund te rikthehen nese duhet.
require_once __DIR__ . '/lib.php';
require_api();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => 'Metodë e pavlefshme.']));
}

$s = student_record($_POST['id'] ?? 0);
if (!$s) {
    http_response_code(404);
    exit(json_encode(['ok' => false, 'error' => 'Studenti nuk u gjet.']));
}

$name = pathinfo($s['docx'] ?: ($s['first_name'] . ' ' . $s['last_name']), PATHINFO_FILENAME);
$trash = DATA_DIR . '/fshire/' . date('Y-m-d His') . ' ' . $name;
if (!ensure_dir($trash)) {
    http_response_code(500);
    exit(json_encode(['ok' => false, 'error' => 'Nuk u krijua dosja e të fshirëve.']));
}

$dir = student_files_dir($s);
foreach (array_filter([$s['docx'] ?? '', $s['photo'] ?? '']) as $f) {
    if (is_file("$dir/$f")) rename("$dir/$f", "$trash/$f");
}
if (is_file(thumb_path($s['id']))) rename(thumb_path($s['id']), "$trash/thumb.jpg");

// te dhenat (me fushat shtese, me emrat e tyre) ruhen ne kosh, pastaj hiqen nga databaza
$labels = array_column(custom_fields(false), 'label', 'id');
$s['custom_named'] = [];
foreach ($s['custom'] as $fid => $v) $s['custom_named'][$labels[$fid] ?? "fusha $fid"] = $v;
file_put_contents("$trash/student.json", json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
student_delete($s['id']);

// dosjet qe mbeten bosh (Bachelor / Master -> Raundi -> viti) fshihen
prune_empty_dirs(student_base($s), $dir);

echo json_encode(['ok' => true]);
