<?php
// Zgjedhja e dosjes ku ruhen studentet
require_once __DIR__ . '/lib.php';
require_page();

$local = is_local_request();

// Kthen listen e disqeve ose nendosjeve si JSON (per butonin "Zgjidh")
if (isset($_GET['browse'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!$local) { http_response_code(403); echo json_encode(['error' => 'Vetëm nga ky kompjuter.']); exit; }

    $path = trim($_GET['browse']);
    $dirs = [];
    if ($path === '' || !is_dir($path)) {
        foreach (range('A', 'Z') as $l) {
            if (is_dir("$l:\\")) $dirs[] = ['name' => "$l:", 'path' => "$l:\\"];
        }
        echo json_encode(['path' => '', 'parent' => null, 'dirs' => $dirs], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $p = rtrim(str_replace('/', '\\', realpath($path) ?: $path), '\\');
    $isDrive = (bool)preg_match('/^[A-Z]:$/i', $p);
    foreach (scandir($p . '\\') ?: [] as $d) {
        if ($d === '.' || $d === '..' || $d[0] === '$' || !is_dir($p . '\\' . $d)) continue;
        if (in_array(strtolower($d), ['system volume information', 'windows', 'recovery', 'config.msi'], true)) continue;
        $dirs[] = ['name' => $d, 'path' => $p . '\\' . $d];
    }
    usort($dirs, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
    echo json_encode([
        'path'   => $isDrive ? $p . '\\' : $p,
        'parent' => $isDrive ? '' : dirname($p),
        'dirs'   => $dirs,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Kopjon Word + foto e studenteve nga dosja e vjeter ne te renë (me nendosjet Bachelor / Master).
// Nga dosja e vjeter nuk fshihet asgje.
function copy_students(string $from, string $to): int
{
    $n = 0;
    $all = load_all_students();
    // skedaret qe u perkasin studenteve te tjere ne dosjen e re nuk mbishkruhen
    $claimed = [];
    foreach ($all as $s) {
        if (same_dir(student_base($s), $from)) continue;
        foreach ([$s['docx'] ?? '', $s['photo'] ?? ''] as $f) $claimed[strtolower(student_files_dir($s) . "/$f")] = true;
    }
    foreach ($all as $s) {
        if (!same_dir(student_base($s), $from)) continue;
        $src = student_files_dir($s);
        $moved = ['base' => base_for_record($to)] + $s;
        $dst = student_files_dir($moved);
        $files = array_filter([$s['docx'] ?? '', $s['photo'] ?? '']);
        if (!ensure_dir($dst)) continue;
        foreach ($files as $f) if (isset($claimed[strtolower("$dst/$f")])) continue 2;
        // nese skedari ekziston tashme atje (p.sh. kthim te dosja e meparshme), nuk kopjohet perseri
        foreach ($files as $f) if (is_file("$src/$f") && !file_exists("$dst/$f")) copy("$src/$f", "$dst/$f");
        student_update($s['id'], ['base' => $moved['base']]);
        $n++;
    }
    return $n;
}

$msg = $err = '';
if ($local && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = students_dir();
    if (($_POST['action'] ?? '') === 'reset') {
        $new = DEFAULT_STUDENTS_DIR;
    } else {
        $new = rtrim(trim($_POST['storage_dir'] ?? ''), '/\\');
        if (preg_match('/^[A-Z]:$/i', $new)) $new .= '\\';
    }

    if ($new === '') {
        $err = 'Shkruani ose zgjidhni një dosje.';
    } elseif (!preg_match('#^([A-Z]:[\\\\/]|\\\\\\\\)#i', $new)) {
        $err = 'Shkruani një adresë të plotë, p.sh. D:\\Studentet ose \\\\SERVER\\Studentet';
    } elseif (!is_dir($new) && !@mkdir($new, 0777, true)) {
        $err = 'Dosja nuk ekziston dhe nuk mund të krijohet: ' . $new;
    } elseif (@file_put_contents($new . '/.test_write', 'ok') === false) {
        $err = 'Nuk ka leje shkrimi në këtë dosje: ' . $new;
    } else {
        @unlink($new . '/.test_write');
        $copied = 0;
        if (!empty($_POST['copy_existing']) && !same_dir($old, $new) && is_dir($old)) {
            $copied = copy_students($old, rtrim($new, '/\\'));
        }
        $wasSetup = storage_needs_setup();
        if (set_storage_dir($new)) {
            if ($wasSetup) { header('Location: ./'); exit; } // kompjuter i ri: direkt te regjistrimi
            $msg ='U ruajt. Studentët e rinj do të ruhen në: ' . students_dir()
                 . ($copied ? " — u kopjuan $copied studentë ekzistues." : '');
        } else {
            $err = 'Nuk u ruajt cilësimi (settings.json).';
        }
    }
}

$current = students_dir();
$count = count(current_students());
$setup = !$msg && storage_needs_setup();
?>
<!doctype html>
<html lang="sq">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ku ruhen studentët – CIT</title>
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body>
<header class="top">
  <div class="wrap">
    <h1>INSTITUTI KANADIAN I TEKNOLOGJISË (CIT)</h1>
    <span>Ku ruhen studentët</span>
    <a class="top-link" href="./">← Kthehu te regjistrimi</a>
  </div>
</header>

<main class="wrap">
  <?php if ($setup): ?>
  <section class="card setup">
    <?php if (configured_storage_dir() === null): ?>
      <h2>👋 Mirë se vini!</h2>
      <p>Ky kompjuter (<b><?= h(computer_name()) ?></b>) përdor programin për herë të parë.
         Zgjidhni më poshtë <b>ku do të ruhen studentët</b> (Word + foto), pastaj shtypni <b>💾 Ruaj</b>.</p>
    <?php else: ?>
      <h2>⚠ Dosja nuk u gjet</h2>
      <p>Dosja <code><?= h(configured_storage_dir()) ?></code> nuk gjendet në këtë kompjuter
         (ndoshta disku ose USB nuk është lidhur). Lidheni, ose zgjidhni një dosje tjetër më poshtë.</p>
    <?php endif; ?>
    <?php if ($err): ?><div class="msg err"><?= h($err) ?></div><?php endif; ?>
  </section>
  <?php else: ?>
  <section class="card">
    <h2>Dosja aktuale <span class="muted small">— <?= h(computer_name()) ?></span></h2>
    <div class="current-path">
      <span>📁 Ruhen në: <code><?= h($current) ?></code></span>
      <?php if ($local): ?><a class="btn small" href="open.php" target="hidden-frame">📂 Hap dosjen</a><?php endif; ?>
    </div>
    <p class="muted"><?= $count ?> studentë në këtë dosje.</p>

    <?php if ($msg): ?><div class="msg ok"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="msg err"><?= h($err) ?></div><?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="card">
    <h2><?= $setup ? 'Ku do të ruhen studentët?' : 'Ndrysho dosjen' ?></h2>
    <?php if (!$local): ?>
      <p class="msg err">Dosja mund të ndryshohet vetëm nga kompjuteri ku është instaluar programi (http://localhost/…).</p>
    <?php else: ?>
    <form method="post" id="settings-form">
      <label>Adresa e dosjes (link / shteg)
        <div class="path-row">
          <input name="storage_dir" id="storage_dir" value="<?= h($current) ?>" placeholder="p.sh. D:\Studentet ose \\SERVER\Studentet" required>
          <button type="button" class="btn" id="btn-browse">📂 Zgjidh…</button>
        </div>
      </label>
      <p class="muted hint">
        Shembuj: <code>D:\Studentet 2026</code> · <code>\\SERVER\Regjistrimi</code> (dosje e përbashkët në rrjet) ·
        <code>G:\My Drive\Studentet</code> (Google Drive për kompjuter).
        Nëse dosja nuk ekziston, krijohet automatikisht.
      </p>

      <div class="browser" id="browser" hidden>
        <div class="browser-head">
          <button type="button" class="btn small" id="br-up">⬆ Lart</button>
          <code id="br-path">Ky kompjuter</code>
        </div>
        <ul id="br-list"></ul>
        <div class="browser-foot">
          <button type="button" class="btn small primary" id="br-pick">✔ Zgjidh këtë dosje</button>
          <button type="button" class="btn small" id="br-close">Mbyll</button>
        </div>
      </div>

      <?php if (!$setup): ?>
      <label class="switch">
        <input type="checkbox" name="copy_existing" value="1" checked>
        Kopjo edhe studentët ekzistues në dosjen e re (nuk fshihet asgjë nga dosja e vjetër)
      </label>
      <?php endif; ?>

      <div class="actions">
        <?php if (!$setup && !same_dir($current, DEFAULT_STUDENTS_DIR)): ?>
          <button type="submit" name="action" value="reset" class="btn" formnovalidate>Kthe dosjen e parazgjedhur</button>
        <?php endif; ?>
        <button type="submit" name="action" value="save" class="btn primary">💾 Ruaj</button>
      </div>
    </form>
    <?php endif; ?>
  </section>
</main>
<iframe name="hidden-frame" hidden></iframe>

<script>
const $ = id => document.getElementById(id);
const input = $('storage_dir');
let cur = { path: '', parent: null };

async function browse(path) {
  const r = await fetch('settings.php?browse=' + encodeURIComponent(path));
  cur = await r.json();
  $('br-path').textContent = cur.path || 'Ky kompjuter';
  $('br-up').disabled = cur.parent === null;
  $('br-pick').disabled = !cur.path;
  const ul = $('br-list');
  ul.innerHTML = '';
  if (!cur.dirs.length) ul.innerHTML = '<li class="muted">(nuk ka nëndosje)</li>';
  for (const d of cur.dirs) {
    const li = document.createElement('li');
    li.textContent = (cur.path ? '📁 ' : '💽 ') + d.name;
    li.onclick = () => browse(d.path);
    ul.appendChild(li);
  }
}

if ($('btn-browse')) {
  $('btn-browse').onclick = () => { $('browser').hidden = false; browse(input.value); };
  $('br-up').onclick = () => browse(cur.parent || '');
  $('br-pick').onclick = () => { input.value = cur.path; $('browser').hidden = true; };
  $('br-close').onclick = () => { $('browser').hidden = true; };
}
</script>
</body>
</html>
