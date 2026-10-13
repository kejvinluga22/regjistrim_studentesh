<?php
// Faqja e pergatitjes se databazes: tregon nese MySQL eshte i fikur dhe krijon databazen me nje klik
require_once __DIR__ . '/lib.php';

$error = '';
$status = db_status();
if ($status === 'no_database' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        db_install();
        header('Location: ./');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
if ($status === 'ok') { header('Location: ./'); exit; }
?>
<!doctype html>
<html lang="sq">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Databaza – CIT</title>
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body>
<header class="top">
  <div class="wrap">
    <h1>INSTITUTI KANADIAN I TEKNOLOGJISË (CIT)</h1>
    <span>Regjistrimi i studentëve</span>
  </div>
</header>
<main class="wrap narrow">
  <section class="card setup">
    <?php if ($status === 'no_server'): ?>
      <h2>⚠ MySQL nuk është i ndezur</h2>
      <p>Programi ruan studentët në databazën MySQL të XAMPP-it. Ndizeni:</p>
      <ol class="steps">
        <li>Hapni <b>XAMPP Control Panel</b> (<code>C:\xampp\xampp-control.exe</code>).</li>
        <li>Te rreshti <b>MySQL</b> shtypni <b>Start</b> (bëhet jeshil).</li>
        <li>Shtypni butonin më poshtë.</li>
      </ol>
      <p class="muted small">Lidhja: <?= h(DB_HOST . ':' . DB_PORT) ?>, përdoruesi "<?= h(DB_USER) ?>" (ndryshohet te config.php).</p>
      <div class="actions"><a class="btn primary" href="install.php">↻ Provo përsëri</a></div>
    <?php else: ?>
      <h2>🗄️ Krijo databazën</h2>
      <p>MySQL është gati, por databaza <b><?= h(DB_NAME) ?></b> nuk ekziston ende në këtë kompjuter.
         Shtypni butonin dhe programi e krijon vetë nga skedari <code>database/regjistrimi_cit.sql</code>
         (me programet, nivelet dhe raundet).</p>
      <p class="muted small">Studentët e regjistruar më parë në këtë program kalohen vetë në databazë pas krijimit.</p>
      <?php if ($error): ?><div class="msg err"><?= h($error) ?></div><?php endif; ?>
      <form method="post" class="actions">
        <button type="submit" class="btn primary">🗄️ Krijo databazën</button>
      </form>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
