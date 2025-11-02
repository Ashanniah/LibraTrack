<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

try {
  // Return just what the dropdown needs
  $sql = "SELECT id, name FROM schools ORDER BY name";
  $res = $conn->query($sql);

  $schools = [];
  while ($row = $res->fetch_assoc()) {
    // normalize keys to front-end style if you like
    $schools[] = ['id' => (int)$row['id'], 'name' => $row['name']];
  }

  echo json_encode(['success' => true, 'schools' => $schools], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error'   => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
} finally {
  if (isset($res) && $res instanceof mysqli_result) $res->free();
  if (isset($conn) && $conn instanceof mysqli) $conn->close();
}
