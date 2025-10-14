<?php
// Adjust if your creds differ
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';            // XAMPP default
$DB_NAME = 'libratrack';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false,'error'=>'DB connection failed: '.$e->getMessage()]);
  exit;
}

function json_response($arr, $code=200){
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode($arr);
  exit;
}

// Hook this up to your session later
function requireRole(array $allowed){
  if (!isset($_SESSION)) session_start();
  $role = $_SESSION['role'] ?? strtolower($_COOKIE['role'] ?? '');
  if (!$role) return; // dev: allow everyone
  if (!in_array(strtolower($role), array_map('strtolower',$allowed), true)) {
    json_response(['ok'=>false,'error'=>'Forbidden'], 403);
  }
}
