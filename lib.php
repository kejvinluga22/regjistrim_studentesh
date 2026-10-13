<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Shkronjat me shenja -> shkronja te thjeshta, per emrat shqip dhe te studenteve te huaj
// (shqip, turqisht, polonisht, ceke, kroate/serbe, rumanisht, gjermanisht, nordike, hungarisht, frengjisht, spanjisht...).
// E njejta tabele perdoret edhe ne assets/app.js, qe pamja ne ekran te jete e njejte me emailin e ruajtur.
function translit_map(): array
{
    static $map = null;
    if ($map !== null) return $map;
    $groups = [
        'a' => 'àáâãäåāăą', 'A' => 'ÀÁÂÃÄÅĀĂĄ', 'c' => 'çćĉċč', 'C' => 'ÇĆĈĊČ',
        'd' => 'ďđð', 'D' => 'ĎĐÐ', 'e' => 'èéêëēĕėęě', 'E' => 'ÈÉÊËĒĔĖĘĚ',
        'g' => 'ĝğġģ', 'G' => 'ĜĞĠĢ', 'h' => 'ĥħ', 'H' => 'ĤĦ',
        'i' => 'ìíîïĩīĭįı', 'I' => 'ÌÍÎÏĨĪĬĮİ', 'j' => 'ĵ', 'J' => 'Ĵ', 'k' => 'ķ', 'K' => 'Ķ',
        'l' => 'ĺļľŀł', 'L' => 'ĹĻĽĿŁ', 'n' => 'ñńņňŉ', 'N' => 'ÑŃŅŇ',
        'o' => 'òóôõöøōŏő', 'O' => 'ÒÓÔÕÖØŌŎŐ', 'r' => 'ŕŗř', 'R' => 'ŔŖŘ',
        's' => 'śŝşšș', 'S' => 'ŚŜŞŠȘ', 't' => 'ţťŧț', 'T' => 'ŢŤŦȚ',
        'u' => 'ùúûüũūŭůűų', 'U' => 'ÙÚÛÜŨŪŬŮŰŲ', 'w' => 'ŵ', 'W' => 'Ŵ',
        'y' => 'ýÿŷ', 'Y' => 'ÝŸŶ', 'z' => 'źżž', 'Z' => 'ŹŻŽ',
    ];
    $map = ['æ' => 'ae', 'Æ' => 'Ae', 'œ' => 'oe', 'Œ' => 'Oe', 'ß' => 'ss', 'þ' => 'th', 'Þ' => 'Th'];
    foreach ($groups as $plain => $chars) {
        foreach (mb_str_split($chars) as $ch) $map[$ch] = $plain;
    }
    return $map;
}

// "Ëlira Çela" -> "Elira Cela", "Şahin Yılmaz" -> "Sahin Yilmaz" (vetem shkronja ASCII)
function to_ascii(string $s): string
{
    return preg_replace('/[^A-Za-z \-]/', '', strtr($s, translit_map()));
}

// Emër i pastër për shfaqje: "aNA  " -> "Ana"
function clean_name(string $s): string
{
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    return mb_convert_case(mb_strtolower($s, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}

// Pjesa e emailit: "Ana Maria" -> "anamaria"
function email_part(string $s): string
{
    return strtolower(preg_replace('/[^A-Za-z]/', '', to_ascii($s)));
}

function make_email_local(string $first, string $last): string
{
    return email_part($first) . '.' . email_part($last);
}

// Fjalekalimi nga modeli: "{Emri}.{NUM}" + Ana Hoxha + 1234 -> "Ana.1234"
// (i njejte me passwordFromTemplate() ne assets/app.js)
function make_password(string $template, string $first, string $last, string $digits): string
{
    $f = email_part($first);
    $l = email_part($last);
    $tokens = [
        'Emri' => ucfirst($f), 'emri' => $f, 'EMRI' => strtoupper($f),
        'Mbiemri' => ucfirst($l), 'mbiemri' => $l, 'MBIEMRI' => strtoupper($l),
        'E' => strtoupper(substr($f, 0, 1)), 'M' => strtoupper(substr($l, 0, 1)),
        'e' => substr($f, 0, 1), 'm' => substr($l, 0, 1),
        'NUM' => $digits, 'VITI' => date('Y'),
    ];
    return preg_replace_callback('/\{(\w+)\}/', fn($m) => $tokens[$m[1]] ?? $m[0], $template);
}

// Shifrat fikse per {NUM}: 4 -> 1234, 6 -> 123456, 8 -> 12345678
function fixed_digits(int $length): string
{
    return substr('1234567890', 0, $length);
}

// ---------- Vendndodhja e ruajtjes ----------
// Dosja ku ruhen studentet mbahet ne databaze (cdo kompjuter ka databazen e vet).
// storage_dir bosh ("") = dosja "students" brenda programit.

// settings.json i versionit te vjeter (lexohet vetem per kalimin ne databaze)
function load_settings(): array
{
    if (!is_file(SETTINGS_FILE)) return [];
    $json = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents(SETTINGS_FILE));
    return json_decode($json, true) ?: [];
}

function computer_name(): string
{
    return getenv('COMPUTERNAME') ?: (gethostname() ?: 'ky-kompjuter');
}

// Dosja e zgjedhur (null nese ende nuk eshte zgjedhur)
function configured_storage_dir(): ?string
{
    return setting('storage_dir');
}

function set_storage_dir(string $dir): bool
{
    set_setting('storage_dir', same_dir($dir, DEFAULT_STUDENTS_DIR) ? '' : $dir);
    return true;
}

// Dosja aktuale ku ruhen studentet
function students_dir(): string
{
    $dir = configured_storage_dir() ?? '';
    return $dir !== '' ? rtrim($dir, '/\\') : DEFAULT_STUDENTS_DIR;
}

// A duhet zgjedhur dosja? (kompjuter i ri, ose dosja e zgjedhur nuk gjendet, p.sh. disk/USB i hequr)
function storage_needs_setup(): bool
{
    $dir = configured_storage_dir();
    if ($dir === null) return true;
    return $dir !== '' && !is_dir($dir);
}

function same_dir(string $a, string $b): bool
{
    $norm = fn($p) => rtrim(str_replace('/', '\\', realpath($p) ?: $p), '\\');
    return strcasecmp($norm($a), $norm($b)) === 0;
}

// Disa veprime (hapja e Explorer / Word) lejohen vetem nga ky kompjuter
function is_local_request(): bool
{
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}

// ---------- Raundet, nivelet, programet, fushat shtese (nga databaza) ----------

function rounds(bool $activeOnly = true): array
{
    static $cache = [];
    return $cache[$activeOnly] ??= db()->query('SELECT name FROM rounds' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY sort_order, id')->fetchAll(PDO::FETCH_COLUMN);
}

// [emri i nivelit => dosja], p.sh. ['Bachelor' => 'Bachelor', 'Master of Science' => 'Master']
function level_folders(bool $activeOnly = true): array
{
    static $cache = [];
    return $cache[$activeOnly] ??= db()->query('SELECT name, folder FROM levels' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY sort_order, id')->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Nendosja sipas nivelit: "... (Bachelor)" -> "Bachelor", "... (Master of Science)" -> "Master"
function level_folder(string $program): string
{
    foreach (level_folders(false) as $level => $folder) {
        if (str_ends_with($program, "($level)")) return $folder;
    }
    return '';
}

// Emri i programit ashtu si ruhet te studenti dhe shkruhet ne Word
function program_value(string $name, string $profile, string $level): string
{
    return $profile !== '' ? "$name – $profile ($level)" : "$name ($level)";
}

// Lista e programeve per dropdown: [niveli => [[label, value, isProfile], ...]]
function program_options(): array
{
    static $out = null;
    if ($out !== null) return $out;
    $out = [];
    $rows = db()->query('SELECT p.name, p.profile, l.name AS level FROM programs p JOIN levels l ON l.id = p.level_id
                         WHERE p.active = 1 AND l.active = 1 ORDER BY l.sort_order, l.id, p.sort_order, p.id')->fetchAll();
    foreach ($rows as $r) {
        $isProfile = $r['profile'] !== '';
        $out[$r['level']][] = [$isProfile ? $r['profile'] : $r['name'], program_value($r['name'], $r['profile'], $r['level']), $isProfile];
    }
    return $out;
}

function is_valid_program(string $value): bool
{
    foreach (program_options() as $options) {
        foreach ($options as [, $v]) if ($v === $value) return true;
    }
    return false;
}

// Fushat shtese qe ka shtuar administratori
function custom_fields(bool $activeOnly = true): array
{
    static $cache = [];
    return $cache[$activeOnly] ??= db()->query('SELECT * FROM custom_fields' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY sort_order, id')->fetchAll();
}

// Lexon vlerat e fushave shtese nga formulari (custom[ID]) dhe kontrollon ato te detyrueshmet
function custom_values_from_post(array $post): array
{
    $values = [];
    foreach (custom_fields() as $f) {
        $v = trim((string)($post['custom'][$f['id']] ?? ''));
        if ($f['required'] && $v === '') throw new InvalidArgumentException('Plotësoni fushën "' . $f['label'] . '".');
        if ($v !== '' && $f['type'] === 'select' && !in_array($v, field_options($f), true)) throw new InvalidArgumentException('Zgjidhni një vlerë nga lista për "' . $f['label'] . '".');
        $values[(int)$f['id']] = mb_substr($v, 0, 1000);
    }
    return $values;
}

// Fushat shtese ne formular (regjistrimi dhe dritarja "Ndrysho"): emrat custom[ID]
function custom_inputs_html(): string
{
    $html = '';
    foreach (custom_fields() as $f) {
        $name = 'custom[' . (int)$f['id'] . ']';
        $req = $f['required'] ? ' required' : '';
        $label = h($f['label']) . ($f['required'] ? ' *' : '');
        $wide = $f['type'] === 'textarea' ? ' class="full"' : '';
        $input = match ($f['type']) {
            'textarea' => '<textarea name="' . $name . '" rows="2" maxlength="1000"' . $req . '></textarea>',
            'select'   => '<select name="' . $name . '"' . $req . '><option value="">— Zgjidhni —</option>'
                          . implode('', array_map(fn($o) => '<option value="' . h($o) . '">' . h($o) . '</option>', field_options($f)))
                          . '</select>',
            'number'   => '<input type="number" name="' . $name . '" step="any"' . $req . '>',
            'date'     => '<input type="date" name="' . $name . '"' . $req . '>',
            default    => '<input name="' . $name . '" maxlength="1000"' . $req . '>',
        };
        $html .= "<label$wide>$label$input</label>\n";
    }
    return $html;
}

function field_options(array $f): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$f['options']))));
}

// ---------- Studentet (databaza) ----------
// Word + foto ruhen ne dosje; cdo student mban mend ku jane skedaret e tij:
//   'base' => dosja e studenteve ("" = dosja "students" e programit), 'subfolder' => "2026-2027/Raundi 1/Bachelor"

const STUDENT_COLUMNS = ['first_name', 'last_name', 'email', 'password', 'phone', 'notes', 'academic_year', 'round',
                         'program', 'base', 'subfolder', 'photo', 'docx', 'imported', 'created_at', 'updated_at'];

// Dosja ku jane Word dhe fotoja e ketij studenti
function student_files_dir(array $s): string
{
    $base = student_base($s);
    $sub = $s['subfolder'] ?? '';
    return $sub !== '' ? "$base/$sub" : $base;
}

// Dosja kryesore e studentit; "" = dosja "students" brenda programit (punon edhe kur USB ndryshon germen e diskut)
function student_base(array $s): string
{
    $base = $s['base'] ?? '';
    return $base === '' ? DEFAULT_STUDENTS_DIR : rtrim($base, '/\\');
}

// Si ruhet 'base' te studenti
function base_for_record(string $dir): string
{
    return same_dir($dir, DEFAULT_STUDENTS_DIR) ? '' : $dir;
}

function ensure_dir(string $dir): bool
{
    return is_dir($dir) || @mkdir($dir, 0777, true);
}

// Shton nje student ne databaze; kthen ID-ne e re
function student_insert(array $s): int
{
    $s += ['created_at' => date('Y-m-d H:i:s')];
    $cols = array_values(array_intersect(STUDENT_COLUMNS, array_keys($s)));
    $q = db()->prepare('INSERT INTO students (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')');
    $q->execute(array_map(fn($c) => is_bool($s[$c]) ? (int)$s[$c] : $s[$c], $cols));
    forget_students_cache();
    return (int)db()->lastInsertId();
}

function student_update(int $id, array $s): void
{
    $cols = array_values(array_intersect(STUDENT_COLUMNS, array_keys($s)));
    if (!$cols) return;
    $q = db()->prepare('UPDATE students SET ' . implode(', ', array_map(fn($c) => "`$c` = ?", $cols)) . ' WHERE id = ?');
    $q->execute([...array_map(fn($c) => is_bool($s[$c]) ? (int)$s[$c] : $s[$c], $cols), $id]);
    forget_students_cache();
}

function student_delete(int $id): void
{
    db()->prepare('DELETE FROM students WHERE id = ?')->execute([$id]);
    forget_students_cache();
}

// Ruhen vlerat e fushave shtese te nje studenti
function student_save_custom(int $id, array $values): void
{
    $q = db()->prepare('INSERT INTO student_values (student_id, field_id, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    foreach ($values as $fieldId => $v) $q->execute([$id, $fieldId, $v]);
}

// Vlerat e fushave shtese: [student_id => [field_id => vlera]]
function custom_values(array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return [];
    $out = [];
    $q = db()->query('SELECT student_id, field_id, value FROM student_values WHERE student_id IN (' . implode(',', $ids) . ')');
    foreach ($q as $r) $out[(int)$r['student_id']][(int)$r['field_id']] = $r['value'];
    return $out;
}

// Te dhenat lexohen nga databaza vetem nje here per kerkese
$STUDENTS_CACHE = null;

function load_all_students(): array
{
    global $STUDENTS_CACHE;
    if ($STUDENTS_CACHE !== null) return $STUDENTS_CACHE;
    $rows = db()->query('SELECT * FROM students ORDER BY created_at DESC, id DESC')->fetchAll();
    foreach ($rows as &$r) $r['id'] = (int)$r['id'];
    return $STUDENTS_CACHE = $rows;
}

function forget_students_cache(): void
{
    global $STUDENTS_CACHE;
    $STUDENTS_CACHE = null;
}

// Kthen te dhenat e studentit me fushat shtese (ose null nese nuk ekziston)
function student_record($id): ?array
{
    $id = (int)$id;
    if ($id <= 0) return null;
    $q = db()->prepare('SELECT * FROM students WHERE id = ?');
    $q->execute([$id]);
    $s = $q->fetch();
    if (!$s) return null;
    $s['id'] = $id;
    $s['custom'] = custom_values([$id])[$id] ?? [];
    return $s;
}

// Studentet qe jane ne dosjen aktuale te ketij kompjuteri.
// Para se te lexohen, dosja skanohet: studentet qe gjenden atje (p.sh. te ruajtur nga nje kompjuter
// tjeter ose te kopjuar me dore) shtohen ne liste duke lexuar letren e tyre Word.
function current_students(): array
{
    import_folder_students();
    $dir = students_dir();
    $same = []; // krahasimi i dosjeve behet nje here per cdo dosje, jo per cdo student
    return array_values(array_filter(load_all_students(), function ($s) use ($dir, &$same) {
        $base = student_base($s);
        if (!($same[$base] ??= same_dir($base, $dir))) return false;
        // studentet qe jane fshire me dore nga dosja (pa Word dhe pa foto) nuk shfaqen
        $files = student_files_dir($s);
        return is_file("$files/" . ($s['docx'] ?? '')) || is_file("$files/" . ($s['photo'] ?? ''));
    }));
}

function norm_path(string $p): string
{
    return strtolower(preg_replace('#/+#', '/', str_replace('\\', '/', $p)));
}

// Gjen letrat Word ne dosjen e studenteve qe programi nuk i njeh ende dhe i shton si studente
function import_folder_students(): int
{
    static $done = false;
    $root = students_dir();
    if ($done || !is_dir($root)) return 0;
    $done = true;

    $known = [];
    $emails = [];
    foreach (load_all_students() as $s) {
        $known[norm_path(student_files_dir($s) . '/' . ($s['docx'] ?? ''))] = true;
        $emails[strtolower($s['email'])] = true;
    }

    $added = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $it->setMaxDepth(4); // <viti>\<raundi>\<niveli>\Emri.docx
    foreach ($it as $file) {
        $name = $file->getFilename();
        if (strtolower($file->getExtension()) !== 'docx' || str_starts_with($name, '~$') || str_starts_with($name, '.')) continue; // skedare te perkohshem
        if (isset($known[norm_path($file->getPathname())])) continue;
        $s = student_from_docx($file->getPathname(), $root);
        if (!$s || isset($emails[$s['email']])) continue; // nuk eshte leter studenti, ose studenti ekziston tashme
        student_insert($s);
        $emails[$s['email']] = true;
        $added++;
    }
    return $added;
}

// Lexon te dhenat e studentit nga letra Word e krijuar nga programi
function student_from_docx(string $path, string $root): ?array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return null;
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if (!$xml) return null;
    $text = html_entity_decode(strip_tags(str_replace('</w:p>', "\n", $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8');

    if (!preg_match('/Adresa e emailit:\s*([^\s@]+@\S+)/u', $text, $em)) return null;
    preg_match('/Fjalëkalimi fillestar:\s*(\S+)/u', $text, $pw);
    preg_match('/I\/E nderuar\s+(.+?),?\s*$/mu', $text, $nm);
    preg_match('/Dega:\s*(.+?)\s*$/mu', $text, $dg);

    // Emri dhe mbiemri: ndahen sipas emailit (p.sh. "Ana Maria Zenelaj" + anamaria.zenelaj)
    $words = preg_split('/\s+/u', trim($nm[1] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
    [$emailFirst] = explode('.', strtolower(strstr($em[1], '@', true)) . '.');
    $first = $words[0] ?? ''; $last = implode(' ', array_slice($words, 1));
    for ($k = 1; $k < count($words); $k++) {
        if (email_part(implode(' ', array_slice($words, 0, $k))) === $emailFirst) {
            $first = implode(' ', array_slice($words, 0, $k));
            $last = implode(' ', array_slice($words, $k));
            break;
        }
    }

    // Viti, raundi dhe niveli nga emrat e dosjeve: <viti>\<raundi>\<niveli>\
    $dir = dirname($path);
    $sub = trim(substr(str_replace('\\', '/', $dir), strlen(str_replace('\\', '/', rtrim($root, '/\\')))), '/');
    $parts = $sub === '' ? [] : explode('/', $sub);
    $year = ''; $round = '';
    foreach ($parts as $p) {
        if (preg_match('/^\d{4}-\d{4}$/', $p)) $year = $p;
        if (in_array($p, rounds(false), true)) $round = $p;
    }

    $base = pathinfo($path, PATHINFO_FILENAME);
    $photo = '';
    foreach (['jpg', 'jpeg', 'png'] as $ext) {
        if (is_file("$dir/$base.$ext")) { $photo = "$base.$ext"; break; }
    }

    return [
        'base'          => base_for_record($root),
        'subfolder'     => $sub,
        'round'         => $round,
        'first_name'    => $first,
        'last_name'     => $last,
        'phone'         => '',
        'academic_year' => $year,
        'program'       => trim($dg[1] ?? ''),
        'email'         => strtolower($em[1]),
        'password'      => $pw[1] ?? '',
        'photo'         => $photo,
        'docx'          => basename($path),
        'created_at'    => date('Y-m-d H:i:s', filemtime($path)),
        'imported'      => 1,
    ];
}

// Studentet sipas zgjedhjeve te eksportit (CSV):
//   ?round=Raundi 1 (bosh = te gjitha raundet), ?level=Bachelor / Master (bosh = te dyja)
function filtered_students(array $q): array
{
    $round = in_array($q['round'] ?? '', rounds(false), true) ? $q['round'] : '';
    $level = in_array($q['level'] ?? '', level_folders(false), true) ? $q['level'] : '';
    $list = array_values(array_filter(current_students(), fn($s) =>
        ($round === '' || ($s['round'] ?? '') === $round) &&
        ($level === '' || level_folder($s['program'] ?? '') === $level)
    ));
    return [$list, $round, $level];
}

// A e ka nje student tjeter kete email?
function email_owner(string $email, int $exceptId = 0): ?array
{
    $q = db()->prepare('SELECT id, first_name, last_name FROM students WHERE email = ? AND id <> ?');
    $q->execute([strtolower($email), $exceptId]);
    return $q->fetch() ?: null;
}

// Gjen një email të lirë: ana.hoxha, ana.hoxha2, ana.hoxha3 ...
function unique_email(string $first, string $last): string
{
    import_folder_students(); // edhe studentet qe jane ne dosje (p.sh. nga kompjuter tjeter)
    $local = make_email_local($first, $last);
    $email = $local . '@' . EMAIL_DOMAIN;
    for ($i = 2; email_owner($email); $i++) {
        $email = $local . $i . '@' . EMAIL_DOMAIN;
    }
    return $email;
}

// Foto e vogel per listen (rreth 4 KB) - ruhet te data/thumbs, jo te dosja e studenteve
function thumb_path($id): string
{
    return DATA_DIR . '/thumbs/' . (int)$id . '.jpg';
}

// Lista perdor foton e vogel; studentet pa te perdorin foton e plote
function thumb_url(array $s): string
{
    return is_file(thumb_path($s['id']))
        ? 'file.php?s=' . (int)$s['id'] . '&thumb=1'
        : student_url($s['id'], $s['photo'] ?? '');
}

// Gjen një emër të lirë për skedarët (Word + foto): "Ana Hoxha", "Ana Hoxha 2" ...
function unique_name(string $first, string $last, string $targetDir): string
{
    $base = trim(preg_replace('/\s+/', ' ', to_ascii(clean_name($first) . ' ' . clean_name($last))));
    if ($base === '') $base = 'Student';
    $name = $base;
    for ($i = 2; glob($targetDir . '/' . glob_escape($name) . '.*'); $i++) {
        $name = $base . ' ' . $i;
    }
    return $name;
}

function glob_escape(string $s): string
{
    return preg_replace('/([*?\[\]])/', '[$1]', $s);
}

// Te dhenat e nje rreshti ne listen e studenteve (te njejta per ruajtjen dhe ndryshimin)
function student_row_data(array $s): array
{
    $id = (int)$s['id'];
    return [
        'id'            => $id,
        'name'          => trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')),
        'email'         => $s['email'] ?? '',
        'password'      => $s['password'] ?? '',
        'round'         => $s['round'] ?? '',
        'program'       => $s['program'] ?? '',
        'level'         => level_folder($s['program'] ?? ''),
        'academic_year' => $s['academic_year'] ?? '',
        'notes'         => $s['notes'] ?? '',
        'created_at'    => $s['created_at'] ?? '',
        'photo_url'     => thumb_url($s),
        'docx_url'      => student_url($id, $s['docx'] ?? ''),
        'print_url'     => 'print.php?s=' . $id,
        'open_url'      => 'open.php?s=' . $id,
    ];
}

// Fshin dosjet qe mbeten bosh pas fshirjes / zhvendosjes se nje studenti:
// nga dosja e studentit lart (Bachelor -> Raundi 1 -> 2026-2027), por kurre vete dosjen kryesore.
// Fshihen vetem dosje plotesisht bosh dhe vetem me emrat qe krijon programi.
function is_program_folder(string $name): bool
{
    return (bool)preg_match('/^\d{4}-\d{4}$/', $name) || in_array($name, rounds(false), true) || in_array($name, level_folders(false), true);
}

function prune_empty_dirs(string $root, string $from): void
{
    $root = norm_path(realpath($root) ?: $root);
    $dir = realpath($from);
    while ($dir && norm_path($dir) !== $root && str_starts_with(norm_path($dir), $root . '/')) {
        if (!is_program_folder(basename($dir)) || !@rmdir($dir)) break; // rmdir deshton nese nuk eshte bosh
        $dir = dirname($dir);
    }
}

// Pastron te gjitha dosjet bosh te programit ne dosjen e studenteve (p.sh. te mbetura nga me pare)
function prune_all_empty_dirs(string $root): void
{
    if (!is_dir($root)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    $it->setMaxDepth(2); // <viti>\<raundi>\<niveli>
    foreach ($it as $f) {
        if ($f->isDir() && is_program_folder($f->getFilename())) @rmdir($f->getPathname());
    }
}

// Skedaret sherbehen nga file.php, qe te punojne edhe kur dosja eshte jashte htdocs
function student_url($id, string $file): string
{
    return 'file.php?s=' . (int)$id . '&f=' . rawurlencode($file);
}

// Vitet akademike per dropdown: nga ACADEMIC_YEARS_FROM deri ACADEMIC_YEARS_AHEAD vite pas vitit aktual
function academic_years(): array
{
    $last = max((int)date('Y'), (int)substr(DEFAULT_ACADEMIC_YEAR, 0, 4)) + ACADEMIC_YEARS_AHEAD;
    $years = [];
    for ($y = ACADEMIC_YEARS_FROM; $y <= $last; $y++) $years[] = $y . '-' . ($y + 1);
    return $years;
}

// Krijon dokumentin Word nga modeli.
// Fushat shtese mund te perdoren ne modelin Word me {{KODI}}, p.sh. {{NR_PERSONAL}}.
function generate_docx(array $s, string $outPath): void
{
    if (!copy(TEMPLATE_DOCX, $outPath)) {
        throw new RuntimeException('Nuk u kopjua modeli Word.');
    }
    $zip = new ZipArchive();
    if ($zip->open($outPath) !== true) {
        throw new RuntimeException('Nuk u hap dokumenti Word.');
    }
    $xml = $zip->getFromName('word/document.xml');
    $x = fn($v) => htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $map = [
        '{{EMRI_PLOTE}}' => $x($s['first_name'] . ' ' . $s['last_name']),
        '{{EMAIL}}'      => $x($s['email']),
        '{{PASSWORD}}'   => $x($s['password']),
        '{{DEGA}}'       => $x($s['program']),
    ];
    foreach (custom_fields(false) as $f) $map['{{' . $f['field_key'] . '}}'] = $x($s['custom'][$f['id']] ?? '');
    $zip->addFromString('word/document.xml', strtr($xml, $map));
    $zip->close();
}

// ---------- Kalimi nga versioni i vjeter (skedare JSON) ne databaze ----------
// Behet vete, nje here: studentet nga data/*.json futen ne databaze dhe skedaret JSON
// zhvendosen ne data/json_backup (si kopje). Edhe dosja e ruajtjes merret nga settings.json.
function migrate_old_data(): void
{
    if (setting('storage_dir') === null) {
        $old = load_settings()['computers'][computer_name()]['storage_dir'] ?? null;
        if ($old !== null) set_setting('storage_dir', $old);
    }
    $files = glob(DATA_DIR . '/*.json') ?: [];
    if (!$files) return;
    ensure_dir(DATA_DIR . '/json_backup');
    foreach ($files as $file) {
        $d = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($file)), true);
        if (is_array($d) && !empty($d['email']) && !email_owner($d['email'])) {
            $newId = student_insert([
                'first_name'    => $d['first_name'] ?? '',
                'last_name'     => $d['last_name'] ?? '',
                'email'         => strtolower($d['email']),
                'password'      => $d['password'] ?? '',
                'phone'         => $d['phone'] ?? '',
                'notes'         => $d['notes'] ?? '',
                'academic_year' => $d['academic_year'] ?? '',
                'round'         => $d['round'] ?? '',
                'program'       => $d['program'] ?? '',
                'base'          => $d['base'] ?? '',
                'subfolder'     => $d['subfolder'] ?? '',
                'photo'         => $d['photo'] ?? '',
                'docx'          => $d['docx'] ?? '',
                'imported'      => !empty($d['imported']) ? 1 : 0,
                'created_at'    => $d['created_at'] ?? date('Y-m-d H:i:s'),
                'updated_at'    => $d['updated_at'] ?? null,
            ]);
            $oldThumb = DATA_DIR . '/thumbs/' . basename($file, '.json') . '.jpg';
            if (is_file($oldThumb)) @rename($oldThumb, thumb_path($newId));
        }
        @rename($file, DATA_DIR . '/json_backup/' . basename($file));
    }
}
