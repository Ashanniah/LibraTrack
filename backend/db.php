<?php
// --- DB config ---
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'libratrack';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// helpers FIRST so they're available even if connection fails
if (!function_exists('json_response')) {
  function json_response(array $arr, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit;
  }
}

// session next
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

// connect
try {
  $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
  // in dev you might log $e->getMessage(); don't leak it to clients
  json_response(['success' => false, 'error' => 'DB connection failed'], 500);
}

// role helpers
if (!function_exists('requireLogin')) {
  function requireLogin(): void {
    if (empty($_SESSION['uid'])) {
      json_response(['success' => false, 'error' => 'Unauthorized'], 401);
    }
  }
}
if (!function_exists('requireRole')) {
  function requireRole(array $allowed): void {
    requireLogin();
    $role = strtolower($_SESSION['role'] ?? '');
    if (!in_array($role, array_map('strtolower',$allowed), true)) {
      json_response(['success' => false, 'error' => 'Forbidden'], 403);
    }
  }
}
