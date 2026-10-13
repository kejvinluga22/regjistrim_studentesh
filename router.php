<?php
// Router per serverin e PHP-se ne versionin portativ (START.bat).
// Ben te njejten pune si .htaccess ne XAMPP: te dhenat e studenteve nuk hapen direkt nga shfletuesi.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

$blocked = preg_match('#^/+(data|students|templates|database)(/|$)|^/+(settings\.json|config\.php|db\.php|auth\.php|lib\.php)$|/\.#i', $path);
$real = realpath(__DIR__ . $path);
foreach (['data', 'students', 'templates', 'database'] as $dir) {
    if ($real && stripos($real, __DIR__ . DIRECTORY_SEPARATOR . $dir) === 0) $blocked = true;
}
if ($real && strcasecmp($real, __DIR__ . DIRECTORY_SEPARATOR . 'settings.json') === 0) $blocked = true;

if ($blocked) {
    http_response_code(403);
    exit('Ndalohet.');
}
return false; // sherbe skedarin normalisht
