<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

// Load all users (admin, librarian, student) — order newest first
$sql = "SELECT id, first_name, middle_name, last_name, suffix, email, role, status, created_at
        FROM users
        ORDER BY id DESC";
$res = $conn->query($sql);

$rows = [];
if ($res) {
  while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
  }
  echo json_encode(["success" => true, "users" => $rows]);
} else {
  json_response(["success"=>false, "message"=>"DB error: ".$conn->error], 500);
}

$conn->close();
