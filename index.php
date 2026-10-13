<?php
require_once __DIR__ . '/lib.php';
require_page(); // databaza gati + administratori i futur
$local = is_local_request();
// Kompjuter i ri (ose dosja nuk gjendet): zgjidh fillimisht ku ruhen studentet
if ($local && storage_needs_setup()) {
    header('Location: settings.php?setup=1');
    exit;
}
prune_all_empty_dirs(students_dir()); // dosje bosh te mbetura (Bachelor / Master / Raundi / viti)
$students = current_students();
?>
<!doctype html>
<html lang="sq">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Regjistrimi i Studentëve – CIT</title>
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body>
<header class="top">
  <div class="wrap">
    <h1>INSTITUTI KANADIAN I TEKNOLOGJISË (CIT)</h1>
    <span>Regjistrimi i studentëve</span>
    <nav class="top-nav">
      <a class="top-link" href="settings.php">📁 Ku ruhen studentët</a>
      <a class="top-link" href="admin.php">⚙ Admin</a>
      <a class="top-link" href="login.php?logout=1" title="Dil nga programi">⎋ Dil</a>
    </nav>
  </div>
</header>

<main class="wrap">
  <?php if (!empty($_SESSION['default_pw'])): ?>
  <div class="msg warn-banner">⚠ Po përdorni fjalëkalimin fillestar <b>admin</b>. Ndryshojeni te <a href="admin.php#llogaria">⚙ Admin → Llogaria</a>.</div>
<?php endif; ?>
  <section class="card">
    <h2>Student i ri</h2>
    <form id="form" autocomplete="off">
      <div class="form-grid">
        <div class="fields">
          <label>Emri *
            <input name="first_name" id="first_name" required>
          </label>
          <label>Mbiemri *
            <input name="last_name" id="last_name" required>
          </label>
          <div class="full rounds">
            <span class="rounds-label">Raundi i regjistrimit *</span>
            <?php foreach (rounds() as $i => $r): ?>
              <label class="round"><input type="radio" name="round" value="<?= h($r) ?>" required> <?= h($r) ?></label>
            <?php endforeach; ?>
          </div>
          <label class="full">Programi / Dega *
            <select name="program" id="program" required>
              <option value="">— Zgjidhni programin —</option>
              <?php foreach (program_options() as $level => $options): ?>
                <optgroup label="<?= h($level) ?> Programs">
                  <?php foreach ($options as [$label, $value, $isProfile]): ?>
                    <option value="<?= h($value) ?>"><?= $isProfile ? '&nbsp;&nbsp;&nbsp;&nbsp;↳ ' : '' ?><?= h($label) ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Telefoni
            <input name="phone" type="tel" placeholder="+355 6X XXX XXXX  ose  +44 / +39 / +90 …">
          </label>
          <label>Viti akademik *
            <select name="academic_year" id="academic_year" required>
              <?php foreach (academic_years() as $y): ?>
                <option value="<?= h($y) ?>" <?= $y === DEFAULT_ACADEMIC_YEAR ? 'selected' : '' ?>><?= h($y) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="full">Shënime
            <input name="notes" maxlength="300" placeholder="p.sh. mungon dokumenti, paguar pjesërisht…">
          </label>
          <?= custom_inputs_html() ?>
        </div>

        <div class="camera">
          <div class="cam-box">
            <video id="video" autoplay playsinline muted></video>
            <img id="photo" alt="">
            <div id="cam-placeholder">Pa foto</div>
          </div>
          <div class="cam-buttons">
            <button type="button" id="btn-cam" class="btn small">📷 Kamera: Fikur</button>
            <button type="button" id="btn-snap" class="btn small primary" hidden>Bëj foton</button>
            <button type="button" id="btn-retake" class="btn small" hidden>Ribëj</button>
          </div>
          <label class="upload">ose ngarko foto
            <input type="file" id="file" accept="image/*">
          </label>
          <select id="cam-select" hidden></select>
        </div>
      </div>

      <div class="creds">
        <div>
          <span class="lbl">Email universitar</span>
          <code id="out-email">emri.mbiemri@<?= h(EMAIL_DOMAIN) ?></code>
        </div>
        <div class="pass-box">
          <span class="lbl">Fjalëkalimi fillestar</span>
          <code id="out-pass">Emri.0000</code>
          <span id="pass-warn" class="warn" hidden>⚠ Gmail kërkon të paktën 8 karaktere</span>

          <div class="pass-row">
            <span class="mini">Modeli</span>
            <select id="pass-template">
              <?php foreach (PASSWORD_TEMPLATES as $t): ?>
                <option value="<?= h($t) ?>"><?= h(make_password($t, 'Emri', 'Mbiemri', '1234')) ?></option>
              <?php endforeach; ?>
              <option value="custom">✎ Model tjetër…</option>
            </select>
          </div>
          <div class="pass-row" id="custom-row" hidden>
            <span class="mini">Model</span>
            <input id="custom-template" maxlength="40" placeholder="{Emri}.{NUM}" spellcheck="false">
          </div>
          <p class="tokens" id="tokens-help" hidden>
            <code>{Emri}</code> <code>{emri}</code> <code>{EMRI}</code> <code>{Mbiemri}</code> <code>{mbiemri}</code>
            <code>{E}</code> <code>{M}</code> <code>{NUM}</code> <code>{VITI}</code> — çdo shenjë tjetër (. @ ! # -) mbetet siç është.
          </p>
          <input type="hidden" name="password_template" id="password_template">

          <div class="pass-row" id="digits-row">
            <span class="mini">Shifrat</span>
            <input name="password_digits" id="digits" inputmode="numeric" title="Shifrat e fjalëkalimit">
            <select id="digits-length" title="Sa shifra">
              <?php foreach (PASSWORD_DIGITS_LENGTHS as $n): ?>
                <option value="<?= $n ?>" <?= $n === DEFAULT_PASSWORD_DIGITS_LENGTH ? 'selected' : '' ?>><?= $n ?> shifra</option>
              <?php endforeach; ?>
            </select>
            <button type="button" id="btn-digits" class="btn small" title="Shifra të reja" hidden>↻</button>
          </div>
          <label class="switch" id="random-row">
            <input type="checkbox" id="random-digits"> Shifra të rastësishme
          </label>
        </div>
      </div>

      <div id="msg" class="msg" hidden></div>

      <div class="actions">
        <button type="submit" class="btn" data-mode="save">💾 Ruaj</button>
        <button type="submit" class="btn primary" data-mode="print">🖨️ Ruaj &amp; printo</button>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="list-head">
      <h2>Studentët e regjistruar (<span id="count"><?= count($students) ?></span>)</h2>
      <div class="list-tools">
        <input type="search" id="search" placeholder="Kërko emër, email, degë…">
        <form class="export" action="export.php" method="get">
          <select name="round" title="Cili raund të eksportohet">
            <option value="">Të gjitha raundet</option>
            <?php foreach (rounds() as $r): ?><option value="<?= h($r) ?>"><?= h($r) ?></option><?php endforeach; ?>
          </select>
          <select name="level" title="Bachelor apo Master">
            <option value="">Bachelor &amp; Master</option>
            <?php foreach (level_folders() as $folder): ?><option value="<?= h($folder) ?>"><?= h($folder) ?></option><?php endforeach; ?>
          </select>
          <button type="submit" class="btn small">⬇ Eksporto CSV</button>
          <button type="button" class="btn small" id="btn-photos-zip" title="Shkarko fotot e studentëve të zgjedhur në një ZIP">🖼️ Shkarko fotot (ZIP)</button>
        </form>
      </div>
    </div>
    <div class="counts-head">
      <label>Viti akademik
        <select id="count-year"></select>
      </label>
    </div>
    <div class="counts" id="counts"></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th></th><th>Emri</th><th>Email</th><th>Fjalëkalimi</th><th>Raundi</th><th>Dega</th><th></th></tr></thead>
        <tbody id="rows">
        <?php foreach ($students as $s): $f = $s['id']; ?>
          <tr data-id="<?= h($f) ?>" data-round="<?= h($s['round'] ?? '') ?>" data-level="<?= h(level_folder($s['program'] ?? '')) ?>" data-year="<?= h($s['academic_year'] ?? '') ?>">
            <td><img class="thumb" src="<?= h(thumb_url($s)) ?>" alt="" loading="lazy"></td>
            <td><?= h($s['first_name'] . ' ' . $s['last_name']) ?><?php if (!empty($s['notes'])): ?><div class="note" title="<?= h($s['notes']) ?>">📝 <?= h($s['notes']) ?></div><?php endif; ?></td>
            <td><?= h($s['email']) ?></td>
            <td><code><?= h($s['password']) ?></code></td>
            <td><?= h($s['round'] ?? '') ?></td>
            <td><?= h($s['program']) ?></td>
            <td class="row-actions">
              <button type="button" class="edit" data-id="<?= h($f) ?>" title="Ndrysho të dhënat e studentit">✏️ Ndrysho</button>
              <a href="<?= h(student_url($f, $s['docx'])) ?>" class="word-link" data-id="<?= h($f) ?>" title="Shiko letrën Word">Word</a>
              <a href="print.php?s=<?= h(rawurlencode($f)) ?>" class="print-link" title="Printo">Printo</a>
              <?php if ($local): ?><a href="open.php?s=<?= h(rawurlencode($f)) ?>" target="hidden-frame" title="Hap dosjen e studentit">Dosja</a><?php endif; ?>
              <button type="button" class="del" data-id="<?= h($f) ?>" data-name="<?= h($s['first_name'] . ' ' . $s['last_name']) ?>" title="Fshi studentin, Word-in dhe foton">🗑 Fshi</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p id="empty" class="muted" <?= $students ? 'hidden' : '' ?>>Ende nuk ka studentë të regjistruar.</p>
    </div>
  </section>
</main>

<canvas id="canvas" hidden></canvas>
<iframe name="hidden-frame" hidden></iframe>
<div id="print-area"></div>

<!-- Dritarja "Ndrysho studentin" -->
<div id="edit-modal" class="modal" hidden>
  <div class="modal-box edit-box" role="dialog" aria-modal="true" aria-labelledby="edit-title">
    <div class="modal-head">
      <b id="edit-title">✏️ Ndrysho studentin</b>
      <button type="button" class="btn small" id="edit-close" title="Mbyll (Esc)">✕</button>
    </div>
    <form id="edit-form" class="edit-form" autocomplete="off">
      <input type="hidden" name="id">
      <div class="edit-grid">
        <label>Emri *<input name="first_name" required></label>
        <label>Mbiemri *<input name="last_name" required></label>
        <label>Raundi *
          <select name="round" required>
            <?php foreach (rounds() as $r): ?><option value="<?= h($r) ?>"><?= h($r) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Viti akademik *
          <select name="academic_year" required>
            <?php foreach (academic_years() as $y): ?><option value="<?= h($y) ?>"><?= h($y) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label class="full">Programi / Dega *
          <select name="program" required>
            <?php foreach (program_options() as $level => $options): ?>
              <optgroup label="<?= h($level) ?> Programs">
                <?php foreach ($options as [$label, $value, $isProfile]): ?>
                  <option value="<?= h($value) ?>"><?= $isProfile ? '&nbsp;&nbsp;&nbsp;&nbsp;↳ ' : '' ?><?= h($label) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Telefoni<input name="phone" type="tel"></label>
        <label class="recalc">&nbsp;
          <button type="button" class="btn small" id="edit-recalc" title="Krijo emailin dhe fjalëkalimin sipas emrit të ri">↻ Email &amp; fjalëkalim sipas emrit</button>
        </label>
        <label>Email *<input name="email" required spellcheck="false"></label>
        <label>Fjalëkalimi *<input name="password" required spellcheck="false"></label>
        <?= custom_inputs_html() ?>
        <label class="full">Shënime<textarea name="notes" rows="2" maxlength="300" placeholder="p.sh. mungon dokumenti…"></textarea></label>
      </div>
      <p class="muted edit-hint">Letra Word krijohet përsëri me të dhënat e reja. Nëse ndryshon emri, viti, raundi ose programi, Word-i dhe fotoja riemërohen / zhvendosen vetë.
        <b>Kujdes:</b> nëse llogaria Gmail është krijuar tashmë, mos e ndryshoni emailin.</p>
      <div id="edit-msg" class="msg err" hidden></div>
      <div class="actions">
        <button type="button" class="btn" id="edit-cancel">Anulo</button>
        <button type="submit" class="btn primary" id="edit-save">💾 Ruaj ndryshimet</button>
      </div>
    </form>
  </div>
</div>

<!-- Dritarja "Shkarko fotot": zgjedh vitin, raundet dhe nivelet -->
<div id="zip-modal" class="modal" hidden>
  <div class="modal-box zip-box" role="dialog" aria-modal="true" aria-labelledby="zip-title">
    <div class="modal-head">
      <b id="zip-title">🖼️ Shkarko fotot e studentëve</b>
      <button type="button" class="btn small" id="zip-close" title="Mbyll (Esc)">✕</button>
    </div>
    <form id="zip-form" class="zip-form">
      <label class="zip-row">Viti akademik
        <select name="year">
          <option value="">Të gjitha vitet</option>
          <?php foreach (academic_years() as $y): ?>
            <option value="<?= h($y) ?>" <?= $y === DEFAULT_ACADEMIC_YEAR ? 'selected' : '' ?>><?= h($y) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <fieldset class="zip-row">
        <legend>Raundi</legend>
        <?php foreach (rounds() as $r): ?>
          <label class="check"><input type="checkbox" name="rounds[]" value="<?= h($r) ?>" checked> <?= h($r) ?></label>
        <?php endforeach; ?>
      </fieldset>
      <fieldset class="zip-row">
        <legend>Niveli</legend>
        <?php foreach (level_folders() as $lv): ?>
          <label class="check"><input type="checkbox" name="levels[]" value="<?= h($lv) ?>" checked> <?= h($lv) ?></label>
        <?php endforeach; ?>
      </fieldset>
      <div class="zip-preview-head">
        <p class="zip-count" id="zip-count">…</p>
        <span class="zip-tools">
          <button type="button" class="btn small" id="zip-all">☑ Të gjitha</button>
          <button type="button" class="btn small" id="zip-none">☐ Asnjë</button>
        </span>
      </div>
      <div class="zip-grid" id="zip-grid"></div>
      <div class="actions">
        <button type="button" class="btn" id="zip-cancel">Anulo</button>
        <button type="submit" class="btn primary" id="zip-go">⬇ Shkarko</button>
      </div>
    </form>
  </div>
</div>
<!-- Dritarja e letres Word (pop-up) -->
<div id="doc-modal" class="modal" hidden>
  <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="doc-title">
    <div class="modal-head">
      <b id="doc-title"></b>
      <div class="modal-actions">
        <button type="button" class="btn small primary" id="doc-open">📄 Hap në Word</button>
        <button type="button" class="btn small" id="doc-print">🖨️ Printo</button>
        <button type="button" class="btn small" id="doc-close" title="Mbyll (Esc)">✕ Mbyll</button>
      </div>
    </div>
    <div class="modal-body"><div class="paper" id="doc-paper"></div></div>
  </div>
</div>
<script>
  const EMAIL_DOMAIN = <?= json_encode(EMAIL_DOMAIN) ?>;
  const TRANSLIT = <?= json_encode(translit_map(), JSON_UNESCAPED_UNICODE) ?>;
  const IS_LOCAL = <?= json_encode($local) ?>;
  const ROUNDS = <?= json_encode(rounds()) ?>;
  const DEFAULT_YEAR = <?= json_encode(DEFAULT_ACADEMIC_YEAR) ?>;
  const LEVELS = <?= json_encode(array_values(level_folders())) ?>;
  const DEFAULT_TEMPLATE = <?= json_encode(PASSWORD_TEMPLATES[0]) ?>;
</script>
<script src="assets/app.js?v=<?= filemtime(__DIR__ . '/assets/app.js') ?>"></script>
</body>
</html>
