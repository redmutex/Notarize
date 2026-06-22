<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'])->notEmpty();

define('APP_NAME',    $_ENV['APP_NAME']    ?? 'Notarize');
define('APP_URL',     $_ENV['APP_URL']     ?? 'https://notarize.onrite.cloud');
define('APP_ENV',     $_ENV['APP_ENV']     ?? 'production');
define('UPLOAD_DIR',  $_ENV['UPLOAD_DIR']  ?? dirname(__DIR__) . '/uploads');
define('MAX_UPLOAD_BYTES', (int)($_ENV['MAX_UPLOAD_MB'] ?? 10) * 1024 * 1024);

// PayPal (client ID is safe to expose to JS)
define('PAYPAL_CLIENT_ID', $_ENV['PAYPAL_CLIENT_ID'] ?? '');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'secure'   => (APP_ENV === 'production'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}
