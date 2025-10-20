<?php
/**
 * DB bootstrap + JSON response helper
 */
require_once __DIR__ . '/config.php';
// --- DB config ---
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'libratrack';

$dsn  = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$opts = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

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
  $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode(['success'=>false,'message'=>'DB connection failed: '.$e->getMessage()]);
  exit;
}

function json_response(array $arr, int $code=200): void {
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode($arr);
  exit;
}
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
