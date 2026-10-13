<?php
// Hyrja e administratorit. Heren e pare: krijimi i llogarise se administratorit.
require_once __DIR__ . '/lib.php';

if (db_status() !== 'ok') { header('Location: install.php'); exit; }
migrate_old_data();

if (isset($_GET['logout'])) {
    admin_logout();
    header('Location: login.php');
    exit;
}

// vetem faqe te ketij programi (jo adresa te jashtme) pas hyrjes
$next = $_GET['next'] ?? './';
if (!preg_match('#^[a-z_]+\.php(\?.*)?$|^\./$#', $next)) $next = './';

$first = !admin_exists();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($first) {
        if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $user)) {
            $error = 'Emri i përdoruesit: 3–40 shkronja, numra, pikë ose vizë.';
        } elseif (strlen($pass) < 6) {
            $error = 'Fjalëkalimi duhet të ketë të paktën 6 karaktere.';
        } elseif ($pass !== ($_POST['password2'] ?? '')) {
            $error = 'Fjalëkalimet nuk përputhen.';
        } else {
            db()->prepare('INSERT INTO admin (username, password_hash) VALUES (?, ?)')->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);
            admin_login($user, $pass);
            header('Location: ' . $next);
            exit;
        }
    } elseif (admin_login($user, $pass)) {
        header('Location: ' . $next);
        exit;
    } else {
        $error = 'Emri i përdoruesit ose fjalëkalimi është i gabuar.';
        usleep(400000); // ngadalëson provat e shumta
    }
}
if (!$first && is_logged_in()) { header('Location: ' . $next); exit; }
?>
<!doctype html>
<html lang="sq">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $first ? 'Krijo administratorin' : 'Hyrja' ?> – CIT</title>
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
  <section class="card login-card">
    <?php if ($first): ?>
      <h2>👤 Krijo llogarinë e administratorit</h2>
      <p class="muted">Herën e parë: zgjidhni emrin dhe fjalëkalimin me të cilin do të hyni në program.</p>
    <?php else: ?>
      <h2>🔒 Hyrja</h2>
    <?php endif; ?>
    <?php if ($error): ?><div class="msg err"><?= h($error) ?></div><?php endif; ?>
    <form method="post" class="login-form" autocomplete="off">
      <label>Emri i përdoruesit
        <input name="username" required autofocus value="<?= h($_POST['username'] ?? ($first ? 'admin' : '')) ?>">
      </label>
      <label>Fjalëkalimi
        <input name="password" type="password" required <?= $first ? 'minlength="6"' : '' ?>>
      </label>
      <?php if ($first): ?>
        <label>Përsërit fjalëkalimin
          <input name="password2" type="password" required minlength="6">
        </label>
      <?php endif; ?>
      <button type="submit" class="btn primary"><?= $first ? '✔ Krijo dhe hyr' : 'Hyr' ?></button>
    </form>
  </section>
</main>
</body>
</html>
