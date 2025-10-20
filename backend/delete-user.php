<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

// Method guard
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Method not allowed']);
  exit;
}

// Read JSON or form body
$raw  = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data) || empty($data)) $data = $_POST;

$id = (int)($data['id'] ?? 0);
if ($id <= 0) {
  http_response_code(422);
  echo json_encode(['success' => false, 'message' => 'Invalid user id.']);
  exit;
}

// Optional safety rails:
// 1) Prevent deleting yourself
if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $id) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => "You can't delete your own account."]);
  exit;
}

// 2) Prevent deleting admins (uncomment if desired)
$chk = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
$chk->bind_param("i", $id);
$chk->execute();
$res = $chk->get_result();
if ($res && ($row = $res->fetch_assoc()) && strtolower((string)$row['role']) === 'admin') {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => "Admin accounts can't be deleted."]);
  $chk->close();
  exit;
}
$chk->close();

// Delete
$stmt = $conn->prepare("DELETE FROM users WHERE id = ? LIMIT 1");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Server error (prepare): '.$conn->error]);
  exit;
}
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
  echo json_encode(['success' => true, 'deleted' => $stmt->affected_rows]);
} else {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Server error: '.$stmt->error]);
}

$stmt->close();
$conn->close();
