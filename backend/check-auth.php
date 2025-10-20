<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

if (empty($_SESSION['uid'])) {
  http_response_code(401);
  echo json_encode(['success' => false]);
  exit;
}

$stmt = $conn->prepare(
  "SELECT id, first_name, last_name, email, role, status
   FROM users WHERE id = ? LIMIT 1"
);
$stmt->bind_param('i', $_SESSION['uid']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || strtolower((string)$user['status']) !== 'active') {
  http_response_code(401);
  echo json_encode(['success' => false]);
  exit;
}

echo json_encode(['success' => true, 'user' => $user]);
