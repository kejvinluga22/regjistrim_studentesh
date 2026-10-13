<?php
require_once __DIR__ . '/lib.php';
require_page();

$s = student_record($_GET['s'] ?? 0);
if (!$s) {
    http_response_code(404);
    exit('Studenti nuk u gjet.');
}
?>
<!doctype html>
<html lang="sq">
<head>
<meta charset="utf-8">
<title><?= h($s['first_name'] . ' ' . $s['last_name']) ?></title>
<style>
  @page { size: A4; margin: 2cm; }
  /* njesoj si dokumenti Word: Calibri (Body) 12 pt */
  body { font-family: Calibri, Carlito, Arial, sans-serif; font-size: 12pt; line-height: 1.3; color: #000; margin: 0; background: #fff; }
  .page { max-width: 16cm; margin: 0 auto; padding: 2.54cm 0; }
  .title { font-weight: bold; margin: 1.2em 0 1.6em; }
  p { margin: 0 0 8pt; }
  ul { margin: 0 0 8pt; padding-left: 1.6em; }
  li { margin-bottom: 4pt; }
  .bar { position: fixed; top: 10px; right: 10px; display: flex; gap: 8px; }
  .bar a, .bar button { font: 14px system-ui, sans-serif; padding: 8px 14px; border-radius: 6px; border: 1px solid #1d4ed8; background: #1d4ed8; color: #fff; text-decoration: none; cursor: pointer; }
  .bar a { background: #fff; color: #1d4ed8; }
  @media print { .bar { display: none; } .page { padding: 0; } }
</style>
</head>
<body>
<div class="bar">
  <a href="<?= h(student_url($s['id'], $s['docx'])) ?>">Shkarko Word</a>
  <button onclick="window.print()">Printo</button>
</div>
<div class="page">
  <p class="title">INSTITUTI KANADIAN I TEKNOLOGJISË (CIT)</p>
  <p>I/E nderuar <?= h($s['first_name'] . ' ' . $s['last_name']) ?>,</p>
  <p>Ju informojmë se ju është krijuar adresa zyrtare e emailit universitar pranë <b>Institutit Kanadez të Teknologjisë (CIT)</b>.</p>
  <p><b>Kredencialet e llogarisë</b></p>
  <ul>
    <li><b>Adresa e emailit:</b> <?= h($s['email']) ?></li>
    <li><b>Fjalëkalimi fillestar:</b> <?= h($s['password']) ?></li>
    <li><b>Platforma e emailit:</b> GMAIL/UMS</li>
    <li>Ums.cit.edu.al</li>
    <li>Dega: <?= h($s['program']) ?></li>
  </ul>
  <p>Për arsye sigurie, ju lutemi ndryshoni fjalëkalimin fillestar menjëherë pas hyrjes së parë dhe mos i ndani kredencialet tuaja me persona të tjerë.</p>
  <p>Emaili universitar duhet të përdoret për komunikimet zyrtare me universitetin, pedagogët, administratën dhe shërbimet akademike.</p>
</div>
<script>
  if (new URLSearchParams(location.search).has('auto')) window.addEventListener('load', () => window.print());
</script>
</body>
</html>
