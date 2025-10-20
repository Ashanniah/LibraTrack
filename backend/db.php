<?php
/**
 * DB bootstrap + JSON response helper
 */

//require_once __DIR__ . '/config.php';

$configFile = __DIR__ . '/config.php';
if (is_file($configFile)) {
  require_once $configFile;
}


// --- DB config ---
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'libratrack';

$dsn  = 'mysql:host=' . $DB_HOST . ';dbname=' . $DB_NAME . ';charset=utf8mb4';
$opts = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

// -------------------------------------------------------------------
// Helper: JSON Response
// -------------------------------------------------------------------
if (!function_exists('json_response')) {
  // avoid type hints/return types for compatibility with older PHP versions
  function json_response($arr, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit;
  }
}

// -------------------------------------------------------------------
// Session setup
// -------------------------------------------------------------------
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

// -------------------------------------------------------------------
// Database connections (PDO + mysqli)
// -------------------------------------------------------------------
try {
  // PDO connection
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $opts);

  // mysqli connection (for legacy scripts)
  $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  $conn->set_charset('utf8mb4');

} catch (Throwable $e) {
  json_response(['success' => false, 'error' => 'DB connection failed: ' . $e->getMessage()], 500);
}

// -------------------------------------------------------------------
// Role helpers
// -------------------------------------------------------------------
if (!function_exists('requireLogin')) {
  function requireLogin() {
    if (empty($_SESSION['uid'])) {
      json_response(['success' => false, 'error' => 'Unauthorized'], 401);
    }
  }
}

if (!function_exists('requireRole')) {
  function requireRole($allowed) {
    requireLogin();
    $role = strtolower($_SESSION['role'] ?? '');
    if (!in_array($role, array_map('strtolower', $allowed), true)) {
      json_response(['success' => false, 'error' => 'Forbidden'], 403);
    }
  }
}
