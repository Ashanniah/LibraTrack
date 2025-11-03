<?php
// backend/db.php
error_reporting(E_ALL);
ini_set('display_errors', '0');   // keep off in prod; turn to '1' if you want raw errors
ini_set('log_errors', '1');

// ---- JSON helper (idempotent) ----
if (!function_exists('json_response')) {
  function json_response($arr, $code = 200) {
    if (!headers_sent()) {
      http_response_code($code);
      header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($arr);
    exit;
  }
}

// ---- Sessions (cookie visible to /libratrack/*) ----
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',      // critical: allow /libratrack/* to see it
    'domain'   => '',
    'secure'   => false,    // localhost (http)
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

// ---- DB connections ----
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'libratrack';

try {
  $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ]);
} catch (Throwable $e) {
  json_response(['success'=>false,'error'=>'DB (PDO) connection failed','detail'=>$e->getMessage()], 500);
}

$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_errno) {
  json_response(['success'=>false,'error'=>'DB (mysqli) connection failed','detail'=>$conn->connect_error], 500);
}
$conn->set_charset('utf8mb4');
