<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
$u = require_role($conn, ['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_response(['success'=>false,'error'=>'Method not allowed'], 405);
}

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);
if (!is_array($data)) $data = $_POST;

$conn->query("CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(64) PRIMARY KEY,
  `value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $conn->prepare("REPLACE INTO settings (`key`,`value`) VALUES (?,?)");
if (!$stmt) json_response(['success'=>false,'error'=>'Prepare failed','detail'=>$conn->error], 500);

foreach ($data as $k => $v) {
  $key = substr((string)$k, 0, 64);
  $val = is_scalar($v) ? (string)$v : json_encode($v);
  $stmt->bind_param('ss', $key, $val);
  if (!$stmt->execute()) {
    $stmt->close();
    json_response(['success'=>false,'error'=>'Execute failed','detail'=>$conn->error], 500);
  }
}
$stmt->close();

json_response(['success'=>true]);



