<?php
// Ndryshimi i nje studenti.
//   GET  ?s=ID   -> te dhenat e studentit (per dritaren "Ndrysho")
//   POST id + fushat -> ruan ndryshimet: rikrijon letren Word, riemeron / zhvendos Word + foto
//                       nese ndryshon emri, viti, raundi ose niveli, dhe fshin dosjet qe mbeten bosh
require_once __DIR__ . '/lib.php';
require_api();
header('Content-Type: application/json; charset=utf-8');

function fail(string $msg, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $s = student_record($_GET['s'] ?? 0);
    if (!$s) fail('Studenti nuk u gjet.', 404);
    echo json_encode(['ok' => true, 'student' => $s], JSON_UNESCAPED_UNICODE);
    exit;
}

$old = student_record($_POST['id'] ?? 0);
if (!$old) fail('Studenti nuk u gjet.', 404);

$first    = clean_name($_POST['first_name'] ?? '');
$last     = clean_name($_POST['last_name'] ?? '');
$program  = trim($_POST['program'] ?? '');
$round    = trim($_POST['round'] ?? '');
$year     = trim($_POST['academic_year'] ?? '');
$email    = strtolower(trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');

if ($first === '' || $last === '') fail('Emri dhe mbiemri janë të detyrueshëm.');
if (email_part($first) === '' || email_part($last) === '') fail('Shkruani emrin dhe mbiemrin me shkronja latine (si në pasaportë).');
// lejohen edhe raundi / programi / viti i vjeter i studentit, edhe nese jane hequr nga lista
if (!in_array($round, rounds(), true) && $round !== $old['round']) fail('Zgjidhni raundin e regjistrimit.');
if (!is_valid_program($program) && $program !== $old['program']) fail('Zgjidhni programin / degën.');
if (!in_array($year, academic_years(), true) && $year !== $old['academic_year']) fail('Zgjidhni vitin akademik.');
if (!preg_match('/^[a-z0-9._-]+@[a-z0-9.-]+\.[a-z]{2,}$/', $email)) fail('Emaili nuk është i vlefshëm.');
if ($password === '') fail('Fjalëkalimi nuk mund të jetë bosh.');
if ($other = email_owner($email, $old['id'])) fail("Emaili $email i përket një studenti tjetër ({$other['first_name']} {$other['last_name']}).");
try {
    $custom = custom_values_from_post($_POST);
} catch (InvalidArgumentException $e) {
    fail($e->getMessage());
}

// Vendndodhja e re: e njejta dosje kryesore, nendosja sipas vitit / raundit / nivelit
$oldDir = student_files_dir($old);
$new = ['subfolder' => trim($year . '/' . $round . '/' . level_folder($program), '/')] + $old;
$newDir = student_files_dir($new);
if (!ensure_dir($newDir)) fail('Nuk u krijua dosja: ' . $newDir, 500);

// Emri i ri i skedareve (Word + foto me te njejtin emer); mbetet i njejti nese nuk ndryshon asgje
$wanted = trim(preg_replace('/\s+/', ' ', to_ascii("$first $last"))) ?: 'Student';
$oldName = pathinfo($old['docx'], PATHINFO_FILENAME);
$sameDir = norm_path($oldDir) === norm_path($newDir);
$fileName = $wanted;
for ($i = 2; ; $i++) {
    $isSelf = $sameDir && strcasecmp($fileName, $oldName) === 0;
    if ($isSelf || !glob($newDir . '/' . glob_escape($fileName) . '.*')) break;
    $fileName = "$wanted $i";
}

$photoExt = strtolower(pathinfo($old['photo'] ?? '', PATHINFO_EXTENSION)) ?: 'jpg';
$new = array_merge($new, [
    'first_name'    => $first,
    'last_name'     => $last,
    'phone'         => trim($_POST['phone'] ?? ''),
    'academic_year' => $year,
    'round'         => $round,
    'program'       => $program,
    'email'         => $email,
    'password'      => $password,
    'notes'         => mb_substr(trim($_POST['notes'] ?? ''), 0, 500),
    'docx'          => "$fileName.docx",
    'photo'         => ($old['photo'] ?? '') !== '' ? "$fileName.$photoExt" : '',
    'updated_at'    => date('Y-m-d H:i:s'),
    'custom'        => $custom,
]);

$oldDocx = "$oldDir/{$old['docx']}";
$newDocx = "$newDir/{$new['docx']}";
$tmpDocx = "$newDir/.~$fileName.docx";
try {
    // 1) letra e re Word (ne skedar te perkohshem, qe e vjetra te mos humbase nese ka gabim)
    generate_docx($new, $tmpDocx);
    // 2) letra e vjeter hiqet (deshton nese eshte e hapur ne Word: atehere nuk ndryshohet asgje)
    if (is_file($oldDocx) && !@unlink($oldDocx)) throw new RuntimeException('Letra Word e këtij studenti është e hapur në Word. Mbylleni dhe provoni përsëri.');
    if (!rename($tmpDocx, $newDocx)) throw new RuntimeException('Letra e re Word nuk u ruajt.');
} catch (Throwable $e) {
    @unlink($tmpDocx);
    fail('Ndryshimi nuk u ruajt: ' . $e->getMessage(), 500);
}
// 3) fotoja: riemerohet / zhvendoset bashke me letren
$oldPhoto = "$oldDir/" . ($old['photo'] ?? '');
if ($new['photo'] !== '' && is_file($oldPhoto) && norm_path($oldPhoto) !== norm_path("$newDir/{$new['photo']}")) {
    // nese fotoja eshte e hapur ne nje program tjeter, kopjohet (e vjetra hiqet kur te mundet)
    if (!@rename($oldPhoto, "$newDir/{$new['photo']}") && @copy($oldPhoto, "$newDir/{$new['photo']}")) @unlink($oldPhoto);
}

// 4) te dhenat ne databaze
try {
    db()->beginTransaction();
    student_update($old['id'], $new);
    student_save_custom($old['id'], $custom);
    db()->commit();
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    fail('Të dhënat nuk u ruajtën: ' . $e->getMessage(), 500);
}

// 5) dosjet e vjetra qe mbeten bosh fshihen
if (!$sameDir) prune_empty_dirs(student_base($old), $oldDir);

$new['id'] = $old['id'];
echo json_encode(['ok' => true, 'old_id' => $old['id'], 'student' => student_row_data($new)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
