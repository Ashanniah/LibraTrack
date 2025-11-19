<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
$u = require_role($conn, ['admin']);

$conn->query("CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(64) PRIMARY KEY,
  `value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$res = $conn->query("SELECT `key`, `value` FROM settings");
$settings = [];
while ($row = $res->fetch_assoc()) {
  $settings[$row['key']] = $row['value'];
}

json_response(['success'=>true,'settings'=>$settings]);



