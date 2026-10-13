<?php
// Eksport CSV i të gjithë studentëve (kolonat sipas formatit të Google Admin për ngarkim në grup)
require_once __DIR__ . '/lib.php';
require_api();

[$students, $round, $level] = filtered_students($_GET);
$fields = custom_fields();

$parts = array_filter(['studentet', str_replace(' ', '_', $round), $level, date('Y-m-d')]);
$file = implode('_', $parts) . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $file . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM qe Excel te lexoje ë/ç sakte
fputcsv($out, [
    'First Name [Required]', 'Last Name [Required]', 'Email Address [Required]', 'Password [Required]',
    'Org Unit Path [Required]', 'Department', 'Change Password at Next Sign-In',
    'Raundi', 'Telefoni', 'Viti akademik', 'Regjistruar me', 'Shënime',
    ...array_column($fields, 'label'), // fushat shtese te administratorit
]);
$values = custom_values(array_column($students, 'id'));
foreach ($students as $s) {
    fputcsv($out, [
        $s['first_name'], $s['last_name'], $s['email'], $s['password'],
        '/', $s['program'], 'TRUE',
        $s['round'] ?? '', $s['phone'] ?? '',
        $s['academic_year'] ?? '', $s['created_at'] ?? '', $s['notes'] ?? '',
        ...array_map(fn($fl) => $values[$s['id']][$fl['id']] ?? '', $fields),
    ]);
}
fclose($out);
