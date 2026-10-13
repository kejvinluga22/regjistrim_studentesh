<?php
// Regjistron nje student te ri: foto + letra Word ne dosje, te dhenat ne databaze
require_once __DIR__ . '/lib.php';
require_api();
header('Content-Type: application/json; charset=utf-8');

function fail(string $msg, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Metodë e pavlefshme.', 405);

$first   = clean_name($_POST['first_name'] ?? '');
$last    = clean_name($_POST['last_name'] ?? '');
$program = trim($_POST['program'] ?? '');
$round   = trim($_POST['round'] ?? '');
$digits  = trim($_POST['password_digits'] ?? '');

if ($first === '' || $last === '') fail('Emri dhe mbiemri janë të detyrueshëm.');
if (email_part($first) === '' || email_part($last) === '') fail('Shkruani emrin dhe mbiemrin me shkronja latine (si në pasaportë).');
if (!in_array($round, rounds(), true)) fail('Zgjidhni raundin e regjistrimit.');
if ($program === '') fail('Zgjidhni programin / degën.');
if (!is_valid_program($program)) fail('Programi i zgjedhur nuk është në listë.');
$template = trim($_POST['password_template'] ?? '');
if ($template === '') $template = PASSWORD_TEMPLATES[0];
if (mb_strlen($template) > 40) fail('Modeli i fjalëkalimit është shumë i gjatë.');
if (!preg_match('/\S/', make_password($template, $first, $last, ''))) fail('Modeli i fjalëkalimit është bosh.');
if (str_contains($template, '{NUM}') && !preg_match('/^\d{1,10}$/', $digits)) fail('Shkruani shifrat e fjalëkalimit (vetëm numra).');
try {
    $custom = custom_values_from_post($_POST);
} catch (InvalidArgumentException $e) {
    fail($e->getMessage());
}

$photo = $_POST['photo'] ?? '';
if (!preg_match('#^data:image/(jpeg|png);base64,#', $photo, $m)) fail('Bëni foton e studentit para se ta ruani.');
$photoBin = base64_decode(substr($photo, strpos($photo, ',') + 1), true);
if ($photoBin === false || @getimagesizefromstring($photoBin) === false) fail('Fotoja nuk është e vlefshme.');
$photoExt = $m[1] === 'png' ? 'png' : 'jpg';

// Foto e vogel per listen (opsionale; maks. 100 KB)
$thumbBin = null;
if (preg_match('#^data:image/jpeg;base64,#', $_POST['thumb'] ?? '')) {
    $t = base64_decode(substr($_POST['thumb'], strpos($_POST['thumb'], ',') + 1), true);
    if ($t !== false && strlen($t) < 100000 && @getimagesizefromstring($t)) $thumbBin = $t;
}

if (storage_needs_setup()) fail('Zgjidhni më parë ku do të ruhen studentët (📁 Ku ruhen studentët).');
$year = trim($_POST['academic_year'] ?? DEFAULT_ACADEMIC_YEAR);
if (!in_array($year, academic_years(), true)) fail('Zgjidhni vitin akademik.');

// Word dhe fotoja ruhen me te njejtin emer, sipas vitit, raundit dhe nivelit:
//   <studentet>/2026-2027/Raundi 1/Bachelor/Ana Hoxha.docx + Ana Hoxha.jpg
$location = [
    'base'      => base_for_record(students_dir()),
    'subfolder' => trim($year . '/' . $round . '/' . level_folder($program), '/'),
];
$dir = student_files_dir($location);
if (!ensure_dir($dir)) fail('Nuk u krijua dosja e studentëve: ' . $dir, 500);

$fileName = unique_name($first, $last, $dir);

$student = $location + [
    'first_name'    => $first,
    'last_name'     => $last,
    'phone'         => trim($_POST['phone'] ?? ''),
    'notes'         => mb_substr(trim($_POST['notes'] ?? ''), 0, 500),
    'academic_year' => $year,
    'round'         => $round,
    'program'       => $program,
    'email'         => unique_email($first, $last),
    'password'      => make_password($template, $first, $last, $digits),
    'photo'         => $fileName . '.' . $photoExt,
    'docx'          => $fileName . '.docx',
    'created_at'    => date('Y-m-d H:i:s'),
    'custom'        => $custom,
];

$photoPath = "$dir/{$student['photo']}";
$docxPath  = "$dir/{$student['docx']}";
$id = 0;
try {
    if (file_put_contents($photoPath, $photoBin) === false) throw new RuntimeException('Fotoja nuk u ruajt.');
    generate_docx($student, $docxPath);
    db()->beginTransaction();
    $id = student_insert($student);
    student_save_custom($id, $custom);
    db()->commit();
    if ($thumbBin !== null && ensure_dir(dirname(thumb_path($id)))) file_put_contents(thumb_path($id), $thumbBin);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    @unlink($photoPath);
    @unlink($docxPath);
    $msg = str_contains($e->getMessage(), 'uq_students_email')
        ? 'Emaili ' . $student['email'] . ' u zu sapo nga një regjistrim tjetër. Shtypni përsëri Ruaj.'
        : 'Gabim gjatë ruajtjes: ' . $e->getMessage();
    fail($msg, 500);
}

echo json_encode(['ok' => true] + student_row_data(['id' => $id] + $student) + [
    'path'  => str_replace('/', DIRECTORY_SEPARATOR, realpath($dir)),
    'files' => [$student['docx'], $student['photo']],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
