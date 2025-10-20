<?php
// Simple .env loader
$env = [];
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
  foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    [$k, $v] = array_map('trim', explode('=', $line, 2));
    $env[$k] = $v;
  }
}
function env($key, $default=null){ global $env; return $env[$key] ?? $default; }

// DB constants for db.php
define('DB_HOST', env('DB_HOST','127.0.0.1'));
define('DB_NAME', env('DB_NAME','libratrack'));
define('DB_USER', env('DB_USER','root'));
define('DB_PASS', env('DB_PASS',''));

// App
define('APP_URL', env('APP_URL', 'http://localhost/libratrack'));

// SMTP
define('SMTP_HOST', env('SMTP_HOST'));
define('SMTP_PORT', (int)env('SMTP_PORT', 587));
define('SMTP_SECURE', env('SMTP_SECURE','tls')); // tls or ssl
define('SMTP_USER', env('SMTP_USER'));
define('SMTP_PASS', env('SMTP_PASS'));
define('SMTP_FROM', env('SMTP_FROM', SMTP_USER));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'LibraTrack'));

// Useful flags in dev
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
