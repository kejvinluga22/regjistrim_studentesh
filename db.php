<?php
// Lidhja me databazen MySQL / MariaDB
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    return $pdo;
}

// Gjendja e databazes: 'ok' | 'no_server' (MySQL i fikur) | 'no_database' (duhet krijuar)
function db_status(): string
{
    try {
        $server = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
    } catch (Throwable $e) {
        return 'no_server';
    }
    $q = $server->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name IN (\'students\', \'admin\', \'programs\', \'rounds\', \'levels\', \'custom_fields\', \'student_values\', \'settings\')');
    $q->execute([DB_NAME]);
    return (int)$q->fetchColumn() === 8 ? 'ok' : 'no_database';
}

// Krijon databazen dhe tabelat nga database/regjistrimi_cit.sql (komandat ndahen me ";" ne fund te rreshtit)
function db_install(): void
{
    $server = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $sql = file_get_contents(DB_SQL_FILE);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql); // komentet
    $sql = str_replace('`regjistrimi_cit`', '`' . str_replace('`', '', DB_NAME) . '`', $sql); // emri nga config.php
    foreach (preg_split('/;\s*$/m', $sql) as $stmt) {
        if (trim($stmt) !== '') $server->exec($stmt);
    }
}

// Cilesimet e programit (tabela "settings")
function setting(string $key, ?string $default = null): ?string
{
    $q = db()->prepare('SELECT v FROM settings WHERE k = ?');
    $q->execute([$key]);
    $v = $q->fetchColumn();
    return $v === false ? $default : $v;
}

function set_setting(string $key, ?string $value): void
{
    db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)')->execute([$key, $value]);
}
