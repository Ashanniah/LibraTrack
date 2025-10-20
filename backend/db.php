<?php
/**
 * DB bootstrap + JSON response helper
 */
require_once __DIR__ . '/config.php';

$dsn  = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$opts = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

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
