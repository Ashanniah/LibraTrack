<?php
/**
 * LibraTrack config.php
 * - Loads /.env (project root)
 * - Provides env() helper
 * - Defines DB + SMTP + APP constants
 * - Production-safe error handling
 */

// -----------------------------
// 1) Simple .env loader
// -----------------------------
$env = [];
$envPath = __DIR__ . '/../.env';

if (file_exists($envPath)) {
  foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);

    // Skip empty and comment lines
    if ($line === '' || str_starts_with($line, '#')) continue;

    // Support: KEY=VALUE (VALUE may include '=')
    $parts = explode('=', $line, 2);
    if (count($parts) !== 2) continue;

    $k = trim($parts[0]);
    $v = trim($parts[1]);

    // Strip optional quotes: "value" or 'value'
    if ((str_starts_with($v, '"') && str_ends_with($v, '"')) ||
        (str_starts_with($v, "'") && str_ends_with($v, "'"))) {
      $v = substr($v, 1, -1);
    }

    $env[$k] = $v;
  }
}

function env($key, $default = null) {
  global $env;
  return array_key_exists($key, $env) ? $env[$key] : $default;
}

// -----------------------------
// 2) App settings
// -----------------------------
define('APP_NAME', env('APP_NAME', 'LibraTrack'));
define('APP_ENV', env('APP_ENV', 'production'));
define('APP_DEBUG', filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN));
define('APP_URL', env('APP_URL', 'http://localhost/libratrack'));

// Hard fail if production but missing .env (prevents silent bad defaults)
if (APP_ENV === 'production' && !file_exists($envPath)) {
  http_response_code(500);
  die('Server misconfiguration: missing .env');
}

// -----------------------------
// 3) Error handling (safe in prod)
// -----------------------------
if (APP_ENV === 'local' || APP_ENV === 'development' || APP_DEBUG === true) {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
} else {
  ini_set('display_errors', '0');
  ini_set('display_startup_errors', '0');
  error_reporting(E_ALL);
  ini_set('log_errors', '1');

  // Optional: write PHP errors to a file (ensure it's not web-accessible)
  $logPath = env('PHP_ERROR_LOG', '');
  if (!empty($logPath)) {
    ini_set('error_log', $logPath);
  }
}

// -----------------------------
// 4) Database constants
// Supports both:
// - DB_NAME / DB_USER / DB_PASS (your custom keys)
// - DB_DATABASE / DB_USERNAME / DB_PASSWORD (Laravel-style keys)
// -----------------------------
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', (int)env('DB_PORT', 3306));

define('DB_NAME', env('DB_NAME', env('DB_DATABASE', 'libratrack')));
define('DB_USER', env('DB_USER', env('DB_USERNAME', 'root')));
define('DB_PASS', env('DB_PASS', env('DB_PASSWORD', '')));

// -----------------------------
// 5) SMTP constants
// Supports both:
// - SMTP_* (your custom keys)
// - MAIL_* (Laravel-style keys)
// -----------------------------
define('SMTP_HOST', env('SMTP_HOST', env('MAIL_HOST', '')));
define('SMTP_PORT', (int)env('SMTP_PORT', env('MAIL_PORT', 587)));
define('SMTP_SECURE', env('SMTP_SECURE', env('MAIL_ENCRYPTION', 'tls'))); // tls or ssl

define('SMTP_USER', env('SMTP_USER', env('MAIL_USERNAME', '')));
define('SMTP_PASS', env('SMTP_PASS', env('MAIL_PASSWORD', '')));

define('SMTP_FROM', env('SMTP_FROM', env('MAIL_FROM_ADDRESS', SMTP_USER)));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', env('MAIL_FROM_NAME', APP_NAME)));
