<?php
// Serveur local : php -S localhost:4180 -t public public/routeur-dev.php
$f = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file($f) && !str_ends_with($f, '.php')) return false;
if (preg_match('#^/dev/paiement/(\w+)$#', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), $m)) { require __DIR__ . '/../app/dev-paiement.php'; exit; }
require __DIR__ . '/index.php';
