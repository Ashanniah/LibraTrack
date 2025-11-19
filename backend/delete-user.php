<?php
// backend/delete-user.php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Method not allowed']);
  exit;
}

$role = strtolower($_SESSION['role'] ?? '');
$uid  = (int)($_SESSION['uid'] ?? 0);
if ($uid <= 0) {
  http_response_code(401);
  echo json_encode(['success'=>false,'message'=>'Unauthorized']);
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

// ✅ Safety: Prevent self-delete
if (isset($_SESSION['uid']) && (int)$_SESSION['uid'] === $id) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => "You can't delete your own account."]);
  exit;
}

// 🔎 Fetch target user
$stmt = $conn->prepare("SELECT id, role, school_id FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
  http_response_code(404);
  echo json_encode(['success'=>false,'message'=>'User not found']);
  exit;
}
$target = $res->fetch_assoc();
$stmt->close();

// 🔐 Rules based on current role
switch ($role) {
  case 'admin':
    // Optional: prevent deleting other admins
    if (strtolower($target['role']) === 'admin') {
      http_response_code(403);
      echo json_encode(['success' => false, 'message' => "Admin accounts can't be deleted."]);
      exit;
    }
    break;

  case 'librarian':
    // Librarians can delete only their own students
    $stmt = $conn->prepare("SELECT school_id FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stmt->bind_result($schoolId);
    $stmt->fetch();
    $stmt->close();

    if (strtolower($target['role']) !== 'student' || (int)$target['school_id'] !== (int)$schoolId) {
      http_response_code(403);
      echo json_encode(['success' => false, 'message' => "You can only delete students from your own school."]);
      exit;
    }
    break;

  default:
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Access denied.']);
    exit;
}

// 🗑️ Perform soft delete (30 days before permanent deletion)
// First, check if deleted_at column exists, if not, add it
try {
  $checkCol = $conn->query("SHOW COLUMNS FROM users LIKE 'deleted_at'");
  if ($checkCol->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
  }
} catch (Exception $e) {
  // Column might already exist or table structure issue - continue anyway
}

// Set deleted_at to 30 days from now
$deletedAt = date('Y-m-d H:i:s', strtotime('+30 days'));
$stmt = $conn->prepare("UPDATE users SET deleted_at = ? WHERE id = ? LIMIT 1");
$stmt->bind_param("si", $deletedAt, $id);

if ($stmt->execute()) {
  echo json_encode([
    'success'=>true, 
    'deleted'=>$stmt->affected_rows,
    'message'=>'User will be permanently deleted in 30 days.'
  ]);
} else {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Server error: '.$stmt->error]);
}

$stmt->close();
$conn->close();
