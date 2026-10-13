<?php
// Shkarkon fotot e studenteve ne nje ZIP, sipas zgjedhjes ne dritaren "Shkarko fotot":
//   ?year=2026-2027 (bosh = te gjitha vitet) &rounds[]=Raundi 1&rounds[]=Raundi 3 &levels[]=Bachelor
//   check=1  -> lista e studenteve (emri + foto e vogel) per parapamjen ne dritare (JSON)
//   skip[]   -> studentet qe jane hequr nga parapamja (nuk futen ne ZIP)
// Shkarkimi behet me POST, qe lista e te hequrve te mos kete kufi gjatesie.
require_once __DIR__ . '/lib.php';
require_api();

$q = $_POST + $_GET;
$year   = in_array($q['year'] ?? '', academic_years(), true) ? $q['year'] : '';
$rounds = array_values(array_intersect((array)($q['rounds'] ?? []), rounds(false)));
$levels = array_values(array_intersect((array)($q['levels'] ?? []), level_folders(false)));
$skip   = array_flip((array)($q['skip'] ?? []));

// Fotot qe perputhen me zgjedhjen dhe ekzistojne ne disk
$photos = [];
$found = ['year' => [], 'round' => [], 'level' => []];
foreach (current_students() as $s) {
    $lv = level_folder($s['program'] ?? '');
    if ($year !== '' && ($s['academic_year'] ?? '') !== $year) continue;
    if (!in_array($s['round'] ?? '', $rounds, true) || !in_array($lv, $levels, true)) continue;
    if (isset($skip[$s['id']]) && !isset($q['check'])) continue;
    $path = student_files_dir($s) . '/' . ($s['photo'] ?? '');
    if (empty($s['photo']) || !is_file($path)) continue;
    $photos[] = [$s, $path, $lv];
    $found['year'][$s['academic_year'] ?? ''] = true;
    $found['round'][$s['round']] = true;
    $found['level'][$lv] = true;
}

if (isset($q['check'])) {
    header('Content-Type: application/json; charset=utf-8');
    $list = array_map(fn($p) => [
        'id'    => $p[0]['id'],
        'name'  => trim(($p[0]['first_name'] ?? '') . ' ' . ($p[0]['last_name'] ?? '')),
        'info'  => implode(' · ', array_filter([$p[0]['round'] ?? '', $p[2], $p[0]['academic_year'] ?? ''])),
        'thumb' => thumb_url($p[0]),
    ], $photos);
    usort($list, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    echo json_encode(['count' => count($list), 'students' => $list], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if (!$photos) {
    http_response_code(404);
    exit('Nuk ka foto për këtë zgjedhje.');
}

set_time_limit(0);
$tmp = tempnam(sys_get_temp_dir(), 'fotot');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('ZIP nuk u krijua.');
}

// Brenda ZIP-it krijohen dosje vetem kur ka me shume se nje vit / raund / nivel:
// p.sh. Raundi 1 + Raundi 3, vetem Bachelor -> "Raundi 1/Emri.jpg", "Raundi 3/Emri.jpg"
foreach ($photos as [$s, $path, $lv]) {
    $parts = [];
    if (count($found['year']) > 1) $parts[] = $s['academic_year'] ?? '';
    if (count($found['round']) > 1) $parts[] = $s['round'];
    if (count($found['level']) > 1) $parts[] = $lv;
    $parts[] = $s['photo'];
    $name = implode('/', array_filter($parts, fn($p) => $p !== ''));
    $zip->addFile($path, $name);
    $zip->setCompressionName($name, ZipArchive::CM_STORE); // JPG eshte i ngjeshur tashme: me shpejt pa ngjeshje
}
$zip->close();

$label = fn($chosen, $all) => count($chosen) === count($all) ? '' : implode('+', $chosen);
$file = implode('_', array_filter([
    'fotot', $year, str_replace(' ', '', $label($rounds, rounds())), $label($levels, level_folders()), date('Y-m-d'),
])) . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
unlink($tmp);
