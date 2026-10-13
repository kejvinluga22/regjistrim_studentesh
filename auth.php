<?php
// Hyrja (login) e administratorit dhe kontrollet ne fillim te cdo faqeje
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', '43200'); // 12 ore pa u dashur hyrje e re
    session_name('CITREG');
    session_set_cookie_params(['lifetime' => 43200, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function admin_exists(): bool
{
    return (int)db()->query('SELECT COUNT(*) FROM admin')->fetchColumn() > 0;
}

// I futur = sesioni ka nje llogari qe ekziston ende (p.sh. jo pas RESET_ADMIN.bat)
function is_logged_in(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    if (empty($_SESSION['admin_id'])) return $ok = false;
    $q = db()->prepare('SELECT 1 FROM admin WHERE id = ?');
    $q->execute([(int)$_SESSION['admin_id']]);
    return $ok = (bool)$q->fetchColumn();
}

function admin_login(string $username, string $password): bool
{
    $q = db()->prepare('SELECT id, password_hash FROM admin WHERE username = ?');
    $q->execute([trim($username)]);
    $row = $q->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) return false;
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$row['id'];
    $_SESSION['admin_user'] = trim($username);
    $_SESSION['default_pw'] = ($password === 'admin'); // kujtese: fjalekalimi fillestar duhet ndryshuar
    return true;
}

function admin_logout(): void
{
    $_SESSION = [];
    session_destroy();
}

// Per faqet HTML: databaza gati -> te dhenat e vjetra te kaluara -> administratori i futur
function require_page(bool $keepSession = false): void
{
    $status = db_status();
    if ($status !== 'ok') { header('Location: install.php'); exit; }
    migrate_old_data();
    if (!admin_exists() || !is_logged_in()) {
        header('Location: login.php?next=' . rawurlencode(basename($_SERVER['SCRIPT_NAME']) . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '')));
        exit;
    }
    if (!$keepSession) session_write_close(); // sesioni lirohet, qe fotot e listes te ngarkohen njekohesisht
}

// Per kerkesat JSON / skedaret (ruajtje, ndryshim, foto...): pergjigje gabimi ne vend te ridrejtimit
function require_api(): void
{
    try {
        db()->query('SELECT 1 FROM students LIMIT 1'); // kontroll i shpejte (thirret per cdo foto te listes)
        $ready = true;
    } catch (Throwable $e) {
        $ready = false;
    }
    if (!$ready) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['ok' => false, 'error' => 'Databaza nuk është gati. Hapni faqen kryesore për ta rregulluar.']));
    }
    if (!is_logged_in()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['ok' => false, 'error' => 'Sesioni mbaroi. Rifreskoni faqen (F5) dhe hyni përsëri.']));
    }
    session_write_close();
}
