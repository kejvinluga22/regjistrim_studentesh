<?php
// Rivendos llogarine e administratorit ne ate fillestare: perdoruesi "admin", fjalekalimi "admin"
// (kur harrohet fjalekalimi). Studentet dhe te dhenat e tjera NUK preken.
// Punon vetem nga RESET_ADMIN.bat ne kete kompjuter, jo nga shfletuesi.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Ndalohet.');
}
require_once __DIR__ . '/db.php';

try {
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->exec('DELETE FROM admin');
    $pdo->prepare('INSERT INTO admin (id, username, password_hash) VALUES (1, ?, ?)')
        ->execute(['admin', password_hash('admin', PASSWORD_DEFAULT)]);
    $pdo->commit();
} catch (Throwable $e) {
    fwrite(STDERR, "  GABIM: databaza nuk u hap (a eshte ndezur MySQL?)\n  " . $e->getMessage() . "\n");
    exit(1);
}
echo "  Llogaria u rivendos:  perdoruesi = admin   fjalekalimi = admin\n";
echo "  Hyni ne program dhe ndryshojeni fjalekalimin te Admin -> Llogaria.\n";
