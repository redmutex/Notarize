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

// Admin
define('ADMIN_EMAIL',    $_ENV['ADMIN_EMAIL']    ?? 'danial@redmutex.com');

// Mail
define('MAIL_FROM',      $_ENV['MAIL_FROM']      ?? 'noreply@notarize.onrite.cloud');
define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? 'Notarize');
define('MAIL_DRIVER',    $_ENV['MAIL_DRIVER']    ?? 'sendmail'); // 'sendmail' | 'smtp'
define('MAIL_HOST',      $_ENV['MAIL_HOST']      ?? '');
define('MAIL_PORT',      (int)($_ENV['MAIL_PORT'] ?? 587));
define('MAIL_USER',      $_ENV['MAIL_USER']      ?? '');
define('MAIL_PASS',      $_ENV['MAIL_PASS']      ?? '');
define('MAIL_ENCRYPTION',$_ENV['MAIL_ENCRYPTION']?? 'tls'); // 'tls' | 'ssl'

// ── Production hardening ──────────────────────────────────────────────────────

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);

    set_exception_handler(function (\Throwable $e): void {
        error_log('[Notarize] Uncaught ' . get_class($e) . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<title>Error — Notarize</title>'
            . '<style>body{font-family:sans-serif;text-align:center;padding:5rem;color:#333}'
            . 'h2{color:#1a3a5c}a{color:#1a3a5c}</style></head><body>'
            . '<h2>Something went wrong</h2>'
            . '<p>Please try again or <a href="mailto:support@notarize.onrite.cloud">contact support</a>.</p>'
            . '<a href="/">Return home</a></body></html>';
        exit;
    });
}

// ── Security headers (sent before any output) ─────────────────────────────────

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (APP_ENV === 'production') {
        header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
    }
    // CSP: PayPal SDK needs its own frame-src/connect-src; Bootstrap + Chart.js from jsDelivr
    $csp = implode(' ', [
        "default-src 'self';",
        "script-src 'self' 'unsafe-inline' cdn.jsdelivr.net www.paypal.com www.paypalobjects.com https://www.sandbox.paypal.com;",
        "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net;",
        "font-src cdn.jsdelivr.net;",
        "img-src 'self' data: www.paypalobjects.com;",
        "frame-src 'self' www.paypal.com https://www.sandbox.paypal.com;",
        "connect-src 'self' www.paypal.com https://www.sandbox.paypal.com;",
        "object-src 'none';",
        "base-uri 'self';",
        "form-action 'self';",
    ]);
    header('Content-Security-Policy: ' . $csp);
}

// ── Session ───────────────────────────────────────────────────────────────────

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
