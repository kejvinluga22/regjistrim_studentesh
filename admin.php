<?php
// ⚙ Admin: programet, nivelet, raundet, fushat shtese te studenteve, llogaria dhe backup i databazes
require_once __DIR__ . '/lib.php';
require_page(true); // sesioni mbetet i hapur per mesazhin pas ruajtjes

// ---------- Backup i databazes (skedar .sql per transport / ruajtje) ----------
if (isset($_GET['backup'])) {
    $pdo = db();
    $out = "-- Backup i databazes \"" . DB_NAME . "\" — Regjistrimi i Studentëve (CIT)\n-- Krijuar: " . date('Y-m-d H:i:s') . "\n"
         . "-- Importohet ne phpMyAdmin (Import) ose me: mysql -u root < kete_skedar.sql\n\n";
    $out .= file_get_contents(DB_SQL_FILE) . "\n\n-- ===== Te dhenat =====\nSET FOREIGN_KEY_CHECKS = 0;\n";
    foreach (['admin', 'settings', 'levels', 'programs', 'rounds', 'custom_fields', 'students', 'student_values'] as $t) {
        $out .= "\nDELETE FROM `$t`;\n";
        foreach ($pdo->query("SELECT * FROM `$t`") as $row) {
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row));
            $out .= "INSERT INTO `$t` (`" . implode('`, `', array_keys($row)) . "`) VALUES (" . implode(', ', $vals) . ");\n";
        }
    }
    $out .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="regjistrimi_cit_backup_' . date('Y-m-d_His') . '.sql"');
    exit($out);
}

// ---------- Veprimet ----------
$msg = $err = '';
$pdo = db();
$tables = ['programs' => 'level_id', 'rounds' => null, 'custom_fields' => null, 'levels' => null];

function next_sort(string $table, ?string $groupCol = null, $groupVal = null): int
{
    $sql = "SELECT COALESCE(MAX(sort_order), 0) + 1 FROM `$table`" . ($groupCol ? " WHERE `$groupCol` = ?" : '');
    $q = db()->prepare($sql);
    $q->execute($groupCol ? [$groupVal] : []);
    return (int)$q->fetchColumn();
}

// "Nr. personal" -> "NR_PERSONAL" (kodi per modelin Word {{NR_PERSONAL}})
function field_key_for(string $label): string
{
    $key = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '_', to_ascii($label)), '_')) ?: 'FUSHA';
    $base = $key;
    $q = db()->prepare('SELECT COUNT(*) FROM custom_fields WHERE field_key = ?');
    for ($i = 2; $q->execute([$key]) && $q->fetchColumn(); $i++) $key = $base . '_' . $i;
    return $key;
}

// ---------- Programet: grupet (programi + profilet e tij) ----------

// Programet e nje niveli te grupuar: [emri => ['main' => rreshti pa profil | null, 'profiles' => [rreshtat]]]
// Renditja e grupit = renditja e rreshtit te tij te pare.
function level_groups(int $levelId): array
{
    $q = db()->prepare('SELECT * FROM programs WHERE level_id = ? ORDER BY sort_order, id');
    $q->execute([$levelId]);
    $groups = [];
    foreach ($q as $r) {
        $g = &$groups[$r['name']];
        $g ??= ['main' => null, 'profiles' => []];
        if ($r['profile'] === '') $g['main'] = $r; else $g['profiles'][] = $r;
        unset($g);
    }
    return $groups;
}

// Ruhet renditja: grupi i 1-re 100, 101, 102...; grupi i 2-te 200... (programi para profileve te tij)
function save_level_order(array $groups): void
{
    $q = db()->prepare('UPDATE programs SET sort_order = ? WHERE id = ?');
    $gi = 1;
    foreach ($groups as $g) {
        if ($g['main']) $q->execute([$gi * 100, $g['main']['id']]);
        foreach (array_values($g['profiles']) as $k => $p) $q->execute([$gi * 100 + $k + 1, $p['id']]);
        $gi++;
    }
}

// Zhvendos nje element lart / poshte ne nje liste me celesa
function move_in(array $list, $key, bool $up): array
{
    $keys = array_keys($list);
    $i = array_search($key, $keys, true);
    $j = $up ? $i - 1 : $i + 1;
    if ($i === false || $j < 0 || $j >= count($keys)) return $list;
    [$keys[$i], $keys[$j]] = [$keys[$j], $keys[$i]];
    $out = [];
    foreach ($keys as $k) $out[$k] = $list[$k];
    return $out;
}

function level_name(int $levelId): string
{
    $q = db()->prepare('SELECT name FROM levels WHERE id = ?');
    $q->execute([$levelId]);
    return (string)$q->fetchColumn();
}

// Kur riemerohet nje program / profil, perditesohen edhe studentet qe e kane
function rename_program_value(string $old, string $new): int
{
    if ($old === $new) return 0;
    $q = db()->prepare('UPDATE students SET program = ? WHERE program = ?');
    $q->execute([$new, $old]);
    return $q->rowCount();
}

$clean = fn($v, $max) => mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$v)), 0, $max);
// emrat qe behen dosje (raundet, nivelet): pa shenja qe nuk lejohen ne Windows
$folderOk = fn($v) => $v !== '' && !preg_match('#[\\\\/:*?"<>|.]#', $v) && !preg_match('/^\d{4}-\d{4}$/', $v);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    try {
        switch ($a) {
            case 'level_add':
                $name = $clean($_POST['name'] ?? '', 80);
                $folder = $clean($_POST['folder'] ?? '', 40) ?: $name;
                if ($name === '' || !$folderOk($folder)) throw new InvalidArgumentException('Shkruani emrin e nivelit dhe një emër dosjeje të vlefshëm (pa / \\ : * ? " < > | .).');
                $pdo->prepare('INSERT INTO levels (name, folder, sort_order) VALUES (?, ?, ?)')->execute([$name, $folder, next_sort('levels')]);
                $msg = "Niveli \"$name\" u shtua.";
                break;

            case 'program_add':   // program i ri (pa profil) ne fund te nivelit
                $name = $clean($_POST['name'] ?? '', 150);
                $level = (int)($_POST['level_id'] ?? 0);
                if ($name === '' || !$level) throw new InvalidArgumentException('Shkruani emrin e programit.');
                $pdo->prepare('INSERT INTO programs (level_id, name, profile, sort_order) VALUES (?, ?, \'\', 999999)')->execute([$level, $name]);
                save_level_order(level_groups($level));
                $msg = "Programi \"$name\" u shtua.";
                break;

            case 'profile_add':   // profil i ri brenda nje programi
                $name = $clean($_POST['name'] ?? '', 150);
                $profile = $clean($_POST['profile'] ?? '', 150);
                $level = (int)($_POST['level_id'] ?? 0);
                if ($name === '' || $profile === '' || !$level) throw new InvalidArgumentException('Shkruani emrin e profilit.');
                $pdo->prepare('INSERT INTO programs (level_id, name, profile, sort_order) VALUES (?, ?, ?, 999999)')->execute([$level, $name, $profile]);
                save_level_order(level_groups($level));
                $msg = "Profili \"$profile\" u shtua te $name.";
                break;

            case 'group_toggle':  // ndez / fik programin bashke me profilet e tij
                $level = (int)($_POST['level_id'] ?? 0);
                $g = level_groups($level)[$_POST['name'] ?? ''] ?? null;
                if (!$g) throw new InvalidArgumentException('Programi nuk u gjet.');
                $on = $g['main'] ? !$g['main']['active'] : !array_filter(array_column($g['profiles'], 'active'));
                $pdo->prepare('UPDATE programs SET active = ? WHERE level_id = ? AND name = ?')->execute([$on ? 1 : 0, $level, $_POST['name']]);
                $msg = 'Programi "' . $_POST['name'] . '" ' . ($on ? 'u aktivizua.' : 'u çaktivizua (nuk shfaqet më te regjistrimi).');
                break;

            case 'group_move':    // programi (me profilet) lart / poshte
                $level = (int)($_POST['level_id'] ?? 0);
                save_level_order(move_in(level_groups($level), $_POST['name'] ?? '', ($_POST['dir'] ?? '') === 'up'));
                break;

            case 'profile_move':  // profili lart / poshte brenda programit
                $level = (int)($_POST['level_id'] ?? 0);
                $groups = level_groups($level);
                $name = $_POST['name'] ?? '';
                if (isset($groups[$name])) {
                    $byId = array_column($groups[$name]['profiles'], null, 'id');
                    $groups[$name]['profiles'] = array_values(move_in($byId, $id, ($_POST['dir'] ?? '') === 'up'));
                    save_level_order($groups);
                }
                break;

            case 'group_rename':  // riemerto programin (edhe profilet dhe studentet e tij)
                $level = (int)($_POST['level_id'] ?? 0);
                $old = $_POST['old'] ?? '';
                $new = $clean($_POST['name'] ?? '', 150);
                $g = level_groups($level)[$old] ?? null;
                if (!$g || $new === '') throw new InvalidArgumentException('Shkruani emrin e ri.');
                $lvl = level_name($level);
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE programs SET name = ? WHERE level_id = ? AND name = ?')->execute([$new, $level, $old]);
                $n = rename_program_value(program_value($old, '', $lvl), program_value($new, '', $lvl));
                foreach ($g['profiles'] as $p) $n += rename_program_value(program_value($old, $p['profile'], $lvl), program_value($new, $p['profile'], $lvl));
                $pdo->commit();
                $msg = "Programi u riemërtua në \"$new\"" . ($n ? " (" . ($n === 1 ? '1 student u përditësua' : "$n studentë u përditësuan") . ")." : '.');
                break;

            case 'profile_rename':
                $new = $clean($_POST['profile'] ?? '', 150);
                $p = $pdo->prepare('SELECT p.*, l.name AS level FROM programs p JOIN levels l ON l.id = p.level_id WHERE p.id = ? AND p.profile <> \'\'');
                $p->execute([$id]);
                $p = $p->fetch();
                if (!$p || $new === '') throw new InvalidArgumentException('Shkruani emrin e ri të profilit.');
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE programs SET profile = ? WHERE id = ?')->execute([$new, $id]);
                $n = rename_program_value(program_value($p['name'], $p['profile'], $p['level']), program_value($p['name'], $new, $p['level']));
                $pdo->commit();
                $msg = "Profili u riemërtua në \"$new\"" . ($n ? " (" . ($n === 1 ? '1 student u përditësua' : "$n studentë u përditësuan") . ")." : '.');
                break;

            case 'round_add':
                $name = $clean($_POST['name'] ?? '', 60);
                if (!$folderOk($name)) throw new InvalidArgumentException('Shkruani emrin e raundit (pa / \\ : * ? " < > | .).');
                $pdo->prepare('INSERT INTO rounds (name, sort_order) VALUES (?, ?)')->execute([$name, next_sort('rounds')]);
                $msg = "Raundi \"$name\" u shtua.";
                break;

            case 'field_add':
                $label = $clean($_POST['label'] ?? '', 100);
                $type = in_array($_POST['type'] ?? '', ['text', 'number', 'date', 'select', 'textarea'], true) ? $_POST['type'] : 'text';
                $options = trim((string)($_POST['options'] ?? ''));
                if ($label === '') throw new InvalidArgumentException('Shkruani emrin e fushës.');
                if ($type === 'select' && !field_options(['options' => $options])) throw new InvalidArgumentException('Për fushën me listë shkruani zgjedhjet (një për rresht).');
                $pdo->prepare('INSERT INTO custom_fields (label, field_key, type, options, required, sort_order) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$label, field_key_for($label), $type, $type === 'select' ? $options : null, !empty($_POST['required']) ? 1 : 0, next_sort('custom_fields')]);
                $msg = "Fusha \"$label\" u shtua. Shfaqet te formulari i regjistrimit dhe në CSV.";
                break;

            case 'field_options':
                $options = trim((string)($_POST['options'] ?? ''));
                if (!field_options(['options' => $options])) throw new InvalidArgumentException('Shkruani të paktën një zgjedhje.');
                $pdo->prepare('UPDATE custom_fields SET options = ? WHERE id = ? AND type = \'select\'')->execute([$options, $id]);
                $msg = 'Zgjedhjet e listës u ruajtën.';
                break;

            case 'toggle':      // aktiv / jo aktiv (programet, raundet, nivelet, fushat)
            case 'required':    // fushat: e detyrueshme / jo
            case 'move':        // renditja lart / poshte
            case 'delete':
                $t = $_POST['table'] ?? '';
                if (!array_key_exists($t, $tables)) throw new InvalidArgumentException('Veprim i pavlefshëm.');
                if ($a === 'toggle') {
                    $pdo->prepare("UPDATE `$t` SET active = 1 - active WHERE id = ?")->execute([$id]);
                    $msg = 'U ndryshua.';
                } elseif ($a === 'required' && $t === 'custom_fields') {
                    $pdo->prepare('UPDATE custom_fields SET required = 1 - required WHERE id = ?')->execute([$id]);
                    $msg = 'U ndryshua.';
                } elseif ($a === 'move') {
                    $group = $tables[$t];
                    $row = $pdo->prepare("SELECT * FROM `$t` WHERE id = ?");
                    $row->execute([$id]);
                    $row = $row->fetch();
                    if ($row) {
                        $up = ($_POST['dir'] ?? '') === 'up';
                        $sql = "SELECT id, sort_order FROM `$t` WHERE " . ($group ? "`$group` = ? AND " : '')
                             . ($up ? '(sort_order < ? OR (sort_order = ? AND id < ?)) ORDER BY sort_order DESC, id DESC'
                                    : '(sort_order > ? OR (sort_order = ? AND id > ?)) ORDER BY sort_order, id') . ' LIMIT 1';
                        $n = $pdo->prepare($sql);
                        $n->execute([...($group ? [$row[$group]] : []), $row['sort_order'], $row['sort_order'], $id]);
                        if ($nb = $n->fetch()) {
                            // nderrohen vendet; nese kane te njejtin numer, behen te ndryshem
                            $s1 = (int)$nb['sort_order']; $s2 = (int)$row['sort_order'];
                            if ($s1 === $s2) $s1 += $up ? -1 : 1;
                            $pdo->prepare("UPDATE `$t` SET sort_order = ? WHERE id = ?")->execute([$s1, $id]);
                            $pdo->prepare("UPDATE `$t` SET sort_order = ? WHERE id = ?")->execute([$s2, $nb['id']]);
                        }
                    }
                } elseif ($a === 'delete') {
                    // fshihet vetem nese asnje student nuk e perdor; perndryshe vetem caktivizohet
                    $used = 0;
                    if ($t === 'programs') {
                        $p = $pdo->prepare('SELECT p.name, p.profile, l.name AS level FROM programs p JOIN levels l ON l.id = p.level_id WHERE p.id = ?');
                        $p->execute([$id]);
                        if ($p = $p->fetch()) {
                            $u = $pdo->prepare('SELECT COUNT(*) FROM students WHERE program = ?');
                            $u->execute([program_value($p['name'], $p['profile'], $p['level'])]);
                            $used = (int)$u->fetchColumn();
                        }
                    } elseif ($t === 'rounds') {
                        $u = $pdo->prepare('SELECT COUNT(*) FROM students s JOIN rounds r ON r.name = s.round WHERE r.id = ?');
                        $u->execute([$id]);
                        $used = (int)$u->fetchColumn();
                    } elseif ($t === 'levels') {
                        $u = $pdo->prepare('SELECT COUNT(*) FROM programs WHERE level_id = ?');
                        $u->execute([$id]);
                        $used = (int)$u->fetchColumn();
                    }
                    if ($used) throw new InvalidArgumentException("Nuk fshihet: përdoret nga $used " . ($t === 'levels' ? 'programe' : 'studentë') . '. Mund ta bëni "Jo aktiv".');
                    $pdo->prepare("DELETE FROM `$t` WHERE id = ?")->execute([$id]);
                    $msg = 'U fshi.';
                }
                break;

            case 'account':
                $q = $pdo->prepare('SELECT password_hash FROM admin WHERE id = ?');
                $q->execute([$_SESSION['admin_id'] ?? 0]);
                $hash = $q->fetchColumn();
                if (!$hash || !password_verify($_POST['current'] ?? '', $hash)) throw new InvalidArgumentException('Fjalëkalimi aktual është i gabuar.');
                $user = trim($_POST['username'] ?? '');
                if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $user)) throw new InvalidArgumentException('Emri i përdoruesit: 3–40 shkronja, numra, pikë ose vizë.');
                $new = $_POST['new'] ?? '';
                if ($new !== '' && strlen($new) < 6) throw new InvalidArgumentException('Fjalëkalimi i ri duhet të ketë të paktën 6 karaktere.');
                if ($new !== '' && $new !== ($_POST['new2'] ?? '')) throw new InvalidArgumentException('Fjalëkalimet e reja nuk përputhen.');
                $pdo->prepare('UPDATE admin SET username = ?' . ($new !== '' ? ', password_hash = ?' : '') . ' WHERE id = ?')
                    ->execute($new !== '' ? [$user, password_hash($new, PASSWORD_DEFAULT), $_SESSION['admin_id']] : [$user, $_SESSION['admin_id']]);
                if ($new !== '') $_SESSION['default_pw'] = ($new === 'admin');
                $msg = 'Llogaria u përditësua.';
                break;
        }
    } catch (PDOException $e) {
        $err = str_contains($e->getMessage(), 'Duplicate') ? 'Ky emër ekziston tashmë.' : 'Gabim: ' . $e->getMessage();
    } catch (InvalidArgumentException $e) {
        $err = $e->getMessage();
    }
    if (!$err) {
        // PRG: pas ruajtjes kthehet te e njejta pjese e faqes
        $_SESSION['flash'] = $msg;
        header('Location: admin.php' . (isset($_POST['anchor']) ? '#' . preg_replace('/\W/', '', $_POST['anchor']) : ''));
        exit;
    }
}
$msg = $msg ?: ($_SESSION['flash'] ?? '');
unset($_SESSION['flash']);
session_write_close();

$levels = $pdo->query('SELECT * FROM levels ORDER BY sort_order, id')->fetchAll();
$programs = $pdo->query('SELECT * FROM programs ORDER BY sort_order, id')->fetchAll();
$rounds = $pdo->query('SELECT * FROM rounds ORDER BY sort_order, id')->fetchAll();
$fields = $pdo->query('SELECT * FROM custom_fields ORDER BY sort_order, id')->fetchAll();
$byLevel = [];
foreach ($programs as $p) $byLevel[$p['level_id']][] = $p;
$counts = $pdo->query('SELECT program, COUNT(*) FROM students GROUP BY program')->fetchAll(PDO::FETCH_KEY_PAIR);
$admin = $pdo->query('SELECT username FROM admin ORDER BY id LIMIT 1')->fetchColumn();
$total = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$typeNames = ['text' => 'Tekst', 'number' => 'Numër', 'date' => 'Datë', 'select' => 'Listë zgjedhjesh', 'textarea' => 'Tekst i gjatë'];

// butonat e vegjel te nje rreshti (⏸ / ▶, ↑, ↓, 🗑)
function row_buttons(string $table, array $row, string $anchor, bool $canDelete = true): string
{
    $form = fn($action, $label, $title, $extra = '') =>
        '<form method="post" class="inline"><input type="hidden" name="action" value="' . $action . '"><input type="hidden" name="table" value="' . $table . '">'
        . '<input type="hidden" name="id" value="' . (int)$row['id'] . '"><input type="hidden" name="anchor" value="' . $anchor . '">' . $extra
        . '<button class="icon-btn" title="' . h($title) . '"' . ($action === 'delete' ? ' onclick="return confirm(\'Ta fshij?\')"' : '') . '>' . $label . '</button></form>';
    return $form('move', '↑', 'Lart', '<input type="hidden" name="dir" value="up">')
         . $form('move', '↓', 'Poshtë', '<input type="hidden" name="dir" value="down">')
         . str_replace('class="icon-btn"', 'class="switch small' . ($row['active'] ? ' on' : '') . '"', $form('toggle', '<span></span>' . ($row['active'] ? 'Aktiv' : 'Jo aktiv'), $row['active'] ? 'Kliko për ta fshehur (nuk shfaqet më në listë)' : 'Kliko për ta aktivizuar'))
         . ($canDelete ? $form('delete', '🗑', 'Fshi') : '');
}
?>
<!doctype html>
<html lang="sq">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin – CIT</title>
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body>
<header class="top">
  <div class="wrap">
    <h1>INSTITUTI KANADIAN I TEKNOLOGJISË (CIT)</h1>
    <span>⚙ Admin</span>
    <nav class="top-nav">
      <a class="top-link" href="./">← Kthehu te regjistrimi</a>
      <a class="top-link" href="login.php?logout=1">⎋ Dil</a>
    </nav>
  </div>
</header>

<main class="wrap">
  <?php if (!empty($_SESSION['default_pw'])): ?>
  <div class="msg warn-banner">⚠ Po përdorni fjalëkalimin fillestar <b>admin</b>. Ndryshojeni te <a href="admin.php#llogaria">⚙ Admin → Llogaria</a>.</div>
<?php endif; ?>
  <?php if ($msg): ?><div class="msg ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="msg err"><?= h($err) ?></div><?php endif; ?>

  <div class="admin-grid">
    <!-- PROGRAMET -->
    <section class="card wide" id="programet">
      <div class="prog-head">
        <h2>🎓 Programet / Degët</h2>
        <input type="search" id="prog-search" placeholder="🔍 Kërko program ose profil…" autocomplete="off">
      </div>

      <div class="prog-tabs" role="tablist">
        <?php foreach ($levels as $l): $nGroups = count(level_groups((int)$l['id'])); ?>
          <button type="button" class="prog-tab" role="tab" data-level="<?= (int)$l['id'] ?>">
            <?= h($l['name']) ?> <span class="badge"><?= $nGroups ?></span><?= $l['active'] ? '' : ' <span class="muted small">(jo aktiv)</span>' ?>
          </button>
        <?php endforeach; ?>
      </div>

      <?php foreach ($levels as $l): $lid = (int)$l['id']; $groups = level_groups($lid); $gi = 0; ?>
      <div class="prog-panel" data-level="<?= $lid ?>" role="tabpanel" hidden>
        <form method="post" class="prog-new">
          <input type="hidden" name="action" value="program_add"><input type="hidden" name="anchor" value="programet">
          <input type="hidden" name="level_id" value="<?= $lid ?>">
          <input name="name" placeholder="Emri i programit të ri në <?= h($l['name']) ?>, p.sh. Cyber Security" required>
          <button class="btn primary">➕ Shto program</button>
        </form>

        <?php if (!$groups): ?><p class="muted">Ende nuk ka programe në këtë nivel.</p><?php endif; ?>

        <div class="prog-list">
        <?php foreach ($groups as $name => $g):
            $gi++;
            $main = $g['main'];
            $mainValue = program_value($name, '', $l['name']);
            $mainCount = (int)($counts[$mainValue] ?? 0);
            $total = $mainCount;
            foreach ($g['profiles'] as $p) $total += (int)($counts[program_value($name, $p['profile'], $l['name'])] ?? 0);
            $groupOn = $main ? (bool)$main['active'] : (bool)array_filter(array_column($g['profiles'], 'active'));
            $search = mb_strtolower($name . ' ' . implode(' ', array_column($g['profiles'], 'profile')));
            $hidden = fn($extra = []) => '<input type="hidden" name="anchor" value="programet"><input type="hidden" name="level_id" value="' . $lid . '"><input type="hidden" name="name" value="' . h($name) . '">'
                . implode('', array_map(fn($k, $v) => '<input type="hidden" name="' . $k . '" value="' . h($v) . '">', array_keys($extra), $extra));
        ?>
          <article class="prog-card<?= $groupOn ? '' : ' off' ?>" data-search="<?= h($search) ?>">
            <header class="prog-card-head">
              <div class="prog-title">
                <b><?= h($name) ?></b>
                <?php if ($total): ?><span class="badge" title="Studentë të regjistruar në këtë program"><?= $total ?> <?= $total === 1 ? 'student' : 'studentë' ?></span><?php endif; ?>
                <?php if (!$main): ?><span class="muted small">(vetëm me profile)</span><?php endif; ?>
              </div>
              <div class="prog-actions">
                <form method="post" class="inline"><input type="hidden" name="action" value="group_toggle"><?= $hidden() ?>
                  <button class="switch<?= $groupOn ? ' on' : '' ?>" title="<?= $groupOn ? 'Aktiv: shfaqet te regjistrimi. Kliko për ta fshehur.' : 'Jo aktiv: nuk shfaqet te regjistrimi. Kliko për ta aktivizuar.' ?>"><span></span><?= $groupOn ? 'Aktiv' : 'Jo aktiv' ?></button></form>
                <form method="post" class="inline"><input type="hidden" name="action" value="group_move"><?= $hidden(['dir' => 'up']) ?>
                  <button class="icon-btn" title="Lart"<?= $gi === 1 ? ' disabled' : '' ?>>↑</button></form>
                <form method="post" class="inline"><input type="hidden" name="action" value="group_move"><?= $hidden(['dir' => 'down']) ?>
                  <button class="icon-btn" title="Poshtë"<?= $gi === count($groups) ? ' disabled' : '' ?>>↓</button></form>
                <details class="pop">
                  <summary class="icon-btn" title="Riemërto">✏️</summary>
                  <form method="post" class="pop-body"><input type="hidden" name="action" value="group_rename"><input type="hidden" name="anchor" value="programet">
                    <input type="hidden" name="level_id" value="<?= $lid ?>"><input type="hidden" name="old" value="<?= h($name) ?>">
                    <label>Emri i ri<input name="name" value="<?= h($name) ?>" required></label>
                    <?php if ($total): ?><p class="muted small">Përditësohen edhe <?= $total ?> studentët. Letrat Word të krijuara më parë mbeten si ishin.</p><?php endif; ?>
                    <button class="btn small primary">💾 Ruaj</button>
                  </form>
                </details>
                <?php if ($main): ?>
                  <form method="post" class="inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="table" value="programs"><input type="hidden" name="id" value="<?= (int)$main['id'] ?>"><input type="hidden" name="anchor" value="programet">
                    <button class="icon-btn danger" title="<?= $mainCount ? 'Nuk fshihet: ka studentë. Çaktivizojeni.' : 'Fshi programin' ?>"<?= $mainCount ? ' disabled' : ' onclick="return confirm(\'Ta fshij programin ' . h(addslashes($name)) . '?\')"' ?>>🗑</button></form>
                <?php endif; ?>
              </div>
            </header>

            <?php if ($g['profiles']): ?>
            <ul class="prof-list">
              <?php foreach ($g['profiles'] as $k => $p): $pc = (int)($counts[program_value($name, $p['profile'], $l['name'])] ?? 0); ?>
              <li class="<?= $p['active'] ? '' : 'off' ?>">
                <span class="prof-name">↳ <?= h($p['profile']) ?><?php if ($pc): ?> <span class="badge"><?= $pc ?></span><?php endif; ?></span>
                <span class="prog-actions">
                  <form method="post" class="inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="table" value="programs"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="anchor" value="programet">
                    <button class="switch small<?= $p['active'] ? ' on' : '' ?>" title="<?= $p['active'] ? 'Kliko për ta fshehur' : 'Kliko për ta aktivizuar' ?>"><span></span><?= $p['active'] ? 'Aktiv' : 'Jo aktiv' ?></button></form>
                  <form method="post" class="inline"><input type="hidden" name="action" value="profile_move"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><?= $hidden(['dir' => 'up']) ?>
                    <button class="icon-btn" title="Lart"<?= $k === 0 ? ' disabled' : '' ?>>↑</button></form>
                  <form method="post" class="inline"><input type="hidden" name="action" value="profile_move"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><?= $hidden(['dir' => 'down']) ?>
                    <button class="icon-btn" title="Poshtë"<?= $k === count($g['profiles']) - 1 ? ' disabled' : '' ?>>↓</button></form>
                  <details class="pop">
                    <summary class="icon-btn" title="Riemërto">✏️</summary>
                    <form method="post" class="pop-body"><input type="hidden" name="action" value="profile_rename"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="anchor" value="programet">
                      <label>Emri i ri i profilit<input name="profile" value="<?= h($p['profile']) ?>" required></label>
                      <button class="btn small primary">💾 Ruaj</button>
                    </form>
                  </details>
                  <form method="post" class="inline"><input type="hidden" name="action" value="delete"><input type="hidden" name="table" value="programs"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="anchor" value="programet">
                    <button class="icon-btn danger" title="<?= $pc ? 'Nuk fshihet: ka studentë. Çaktivizojeni.' : 'Fshi profilin' ?>"<?= $pc ? ' disabled' : ' onclick="return confirm(\'Ta fshij profilin?\')"' ?>>🗑</button></form>
                </span>
              </li>
              <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <details class="add-prof">
              <summary>➕ Shto profil</summary>
              <form method="post" class="prog-new small">
                <input type="hidden" name="action" value="profile_add"><?= $hidden() ?>
                <input name="profile" placeholder="Emri i profilit, p.sh. Digital Marketing Profile" required>
                <button class="btn small primary">Shto</button>
              </form>
            </details>
          </article>
        <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>

      <p class="muted small prog-help">
        <b>Aktiv / Jo aktiv:</b> programet jo aktive nuk shfaqen te regjistrimi, por studentët e regjistruar mbeten.
        <b>🗑</b> lejohet vetëm kur asnjë student nuk e ka programin. Në Word shkruhet p.sh. "Business Administration – Digital Marketing Profile (Bachelor)".
      </p>
      <p class="muted small" id="prog-noresult" hidden>Asnjë program nuk përputhet me kërkimin.</p>
    </section>

    <!-- RAUNDET -->
    <section class="card" id="raundet">
      <h2>🔁 Raundet</h2>
      <table class="admin-table">
        <?php foreach ($rounds as $r): ?>
          <tr class="<?= $r['active'] ? '' : 'inactive' ?>"><td><b><?= h($r['name']) ?></b></td><td class="row-btns"><?= row_buttons('rounds', $r, 'raundet') ?></td></tr>
        <?php endforeach; ?>
      </table>
      <form method="post" class="admin-add">
        <input type="hidden" name="action" value="round_add"><input type="hidden" name="anchor" value="raundet">
        <input name="name" placeholder="p.sh. Raundi 4" required>
        <button class="btn primary small">➕ Shto</button>
      </form>
    </section>

    <!-- NIVELET -->
    <section class="card" id="nivelet">
      <h2>📚 Nivelet</h2>
      <table class="admin-table">
        <?php foreach ($levels as $l): ?>
          <tr class="<?= $l['active'] ? '' : 'inactive' ?>"><td><b><?= h($l['name']) ?></b> <span class="muted small">→ dosja "<?= h($l['folder']) ?>"</span></td><td class="row-btns"><?= row_buttons('levels', $l, 'nivelet') ?></td></tr>
        <?php endforeach; ?>
      </table>
      <form method="post" class="admin-add">
        <input type="hidden" name="action" value="level_add"><input type="hidden" name="anchor" value="nivelet">
        <input name="name" placeholder="p.sh. PhD" required>
        <input name="folder" placeholder="Dosja (p.sh. PhD)">
        <button class="btn primary small">➕ Shto</button>
      </form>
    </section>

    <!-- FUSHAT SHTESE -->
    <section class="card wide" id="fushat">
      <h2>🧩 Fushat shtesë për studentët</h2>
      <p class="muted small">Shfaqen te formulari i regjistrimit, te dritarja "Ndrysho" dhe në CSV. Në modelin Word mund t'i përdorni me kodin, p.sh. <code>{{NR_PERSONAL}}</code>.</p>
      <?php if ($fields): ?>
      <table class="admin-table">
        <tr class="muted small"><th>Fusha</th><th>Lloji</th><th>Kodi në Word</th><th>E detyrueshme</th><th></th></tr>
        <?php foreach ($fields as $f): ?>
          <tr class="<?= $f['active'] ? '' : 'inactive' ?>">
            <td><b><?= h($f['label']) ?></b>
              <?php if ($f['type'] === 'select'): ?>
                <details class="small"><summary class="muted">Zgjedhjet (<?= count(field_options($f)) ?>)</summary>
                  <form method="post" class="admin-options">
                    <input type="hidden" name="action" value="field_options"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><input type="hidden" name="anchor" value="fushat">
                    <textarea name="options" rows="4"><?= h($f['options']) ?></textarea>
                    <button class="btn small">💾 Ruaj zgjedhjet</button>
                  </form>
                </details>
              <?php endif; ?>
            </td>
            <td class="small"><?= h($typeNames[$f['type']] ?? $f['type']) ?></td>
            <td><code>{{<?= h($f['field_key']) ?>}}</code></td>
            <td>
              <form method="post" class="inline"><input type="hidden" name="action" value="required"><input type="hidden" name="table" value="custom_fields"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><input type="hidden" name="anchor" value="fushat">
                <button class="icon-btn" title="Ndrysho"><?= $f['required'] ? '✔ Po' : '— Jo' ?></button></form>
            </td>
            <td class="row-btns"><?= row_buttons('custom_fields', $f, 'fushat') ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
      <?php else: ?>
        <p class="muted">Ende nuk ka fusha shtesë.</p>
      <?php endif; ?>
      <form method="post" class="admin-add field-add" id="field-form">
        <input type="hidden" name="action" value="field_add"><input type="hidden" name="anchor" value="fushat">
        <input name="label" placeholder="Emri i fushës, p.sh. Nr. personal" required>
        <select name="type" id="field-type">
          <?php foreach ($typeNames as $k => $n): ?><option value="<?= $k ?>"><?= $n ?></option><?php endforeach; ?>
        </select>
        <label class="check"><input type="checkbox" name="required" value="1"> E detyrueshme</label>
        <button class="btn primary small">➕ Shto fushën</button>
        <textarea name="options" id="field-options" rows="3" placeholder="Zgjedhjet, një për rresht (p.sh. Tiranë&#10;Durrës&#10;Shkodër)" hidden></textarea>
      </form>
      <p class="muted small">⚠ Fshirja e një fushe fshin edhe vlerat e saj te të gjithë studentët. Për ta fshehur pa humbur të dhënat, bëjeni "Jo aktiv".</p>
    </section>

    <!-- LLOGARIA -->
    <section class="card" id="llogaria">
      <h2>👤 Llogaria e administratorit</h2>
      <form method="post" class="login-form">
        <input type="hidden" name="action" value="account"><input type="hidden" name="anchor" value="llogaria">
        <label>Emri i përdoruesit<input name="username" value="<?= h($admin) ?>" required></label>
        <label>Fjalëkalimi i ri (bosh = pa ndryshim)<input name="new" type="password" minlength="6" autocomplete="new-password"></label>
        <label>Përsërit fjalëkalimin e ri<input name="new2" type="password" autocomplete="new-password"></label>
        <label>Fjalëkalimi aktual *<input name="current" type="password" required autocomplete="current-password"></label>
        <button class="btn primary">💾 Ruaj</button>
      </form>
    </section>

    <!-- DATABAZA -->
    <section class="card" id="databaza">
      <h2>🗄️ Databaza</h2>
      <p>Databaza <b><?= h(DB_NAME) ?></b> në <?= h(DB_HOST . ':' . DB_PORT) ?> · <b><?= $total ?></b> studentë.</p>
      <p class="muted small">Backup-i përmban gjithçka (studentët, programet, raundet, fushat dhe llogarinë). Fotot dhe letrat Word mbeten në dosjen e studentëve — kopjojini edhe ato.</p>
      <a class="btn primary" href="admin.php?backup=1">⬇ Shkarko backup (.sql)</a>
      <p class="muted small">Për ta rikthyer në një kompjuter tjetër: phpMyAdmin → Import → zgjidhni skedarin .sql.</p>
    </section>
  </div>
</main>
<script>
  // fusha "Listë zgjedhjesh" ka nevojë per zgjedhjet
  const t = document.getElementById('field-type'), o = document.getElementById('field-options');
  t.addEventListener('change', () => { o.hidden = t.value !== 'select'; o.required = t.value === 'select'; });

  // Programet: skedat sipas nivelit (mbahet mend skeda e fundit) dhe kerkimi
  const tabs = [...document.querySelectorAll('.prog-tab')], panels = [...document.querySelectorAll('.prog-panel')];
  const search = document.getElementById('prog-search');
  function showLevel(id) {
    tabs.forEach(b => b.classList.toggle('active', b.dataset.level === id));
    panels.forEach(p => p.hidden = p.dataset.level !== id);
    try { localStorage.setItem('progTab', id); } catch (e) {}
    filterPrograms();
  }
  function filterPrograms() {
    const q = search.value.trim().toLowerCase();
    const panel = panels.find(p => !p.hidden);
    let shown = 0;
    panel?.querySelectorAll('.prog-card').forEach(c => { c.hidden = q && !c.dataset.search.includes(q); if (!c.hidden) shown++; });
    document.getElementById('prog-noresult').hidden = !q || shown > 0;
  }
  tabs.forEach(b => b.addEventListener('click', () => showLevel(b.dataset.level)));
  search.addEventListener('input', filterPrograms);
  let saved = null; try { saved = localStorage.getItem('progTab'); } catch (e) {}
  showLevel(tabs.some(b => b.dataset.level === saved) ? saved : tabs[0]?.dataset.level);
  // vetem nje dritare "Riemerto" e hapur njeherazi
  document.querySelectorAll('details.pop').forEach(d => d.addEventListener('toggle', () => {
    if (d.open) document.querySelectorAll('details.pop[open]').forEach(o => { if (o !== d) o.open = false; });
  }));
</script>
</body>
</html>
