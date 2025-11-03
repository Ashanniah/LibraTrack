<?php
// backend/update-student.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$role = strtolower($_SESSION['role'] ?? '');
$uid  = (int)($_SESSION['uid'] ?? 0);
if ($uid <= 0 || $role !== 'librarian') {
  http_response_code(401);
  echo json_encode(['success'=>false,'message'=>'Unauthorized']);
  exit;
}

$data = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
$id = (int)($data['id'] ?? 0);
if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'Missing ID']); exit; }

// librarian school_id (to scope student_no uniqueness)
$schoolId = 0;
if ($stmt = $conn->prepare("SELECT school_id FROM users WHERE id=? LIMIT 1")) {
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $stmt->bind_result($schoolId);
  $stmt->fetch();
  $stmt->close();
}
$schoolId = (int)$schoolId;

$first  = trim((string)($data['first_name'] ?? ''));
$middle = trim((string)($data['middle_name'] ?? ''));
$last   = trim((string)($data['last_name'] ?? ''));
$suffix = trim((string)($data['suffix'] ?? ''));
$email  = strtolower(trim((string)($data['email'] ?? '')));
$studno = trim((string)($data['student_no'] ?? ''));
$course = trim((string)($data['course'] ?? ''));
$year   = trim((string)($data['year_level'] ?? ''));
$sect   = trim((string)($data['section'] ?? ''));
$status = strtolower(trim((string)($data['status'] ?? 'active')));
$notes  = trim((string)($data['notes'] ?? ''));
$pw     = (string)($data['password'] ?? '');

// minimal validation
if ($first === '' || $last === '') { echo json_encode(['success'=>false,'message'=>'Name is required']); exit; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['success'=>false,'message'=>'Enter a valid email']); exit; }
if ($studno !== '' && !preg_match('/^\d{8}$/', $studno)) {
  echo json_encode(['success'=>false,'message'=>'Student number must be exactly 8 digits']); exit;
}

// uniqueness (exclude current id)
if ($stmt = $conn->prepare("SELECT id FROM users WHERE email=? AND id<>? LIMIT 1")) {
  $stmt->bind_param("si", $email, $id);
  $stmt->execute(); $stmt->store_result();
  if ($stmt->num_rows > 0) { $stmt->close(); echo json_encode(['success'=>false,'message'=>'Email already exists']); exit; }
  $stmt->close();
}
if ($studno !== '' && $stmt = $conn->prepare("SELECT id FROM users WHERE student_no=? AND school_id=? AND id<>? LIMIT 1")) {
  $stmt->bind_param("sii", $studno, $schoolId, $id);
  $stmt->execute(); $stmt->store_result();
  if ($stmt->num_rows > 0) { $stmt->close(); echo json_encode(['success'=>false,'message'=>'Student number already exists in this school']); exit; }
  $stmt->close();
}

$set = "first_name=?, middle_name=?, last_name=?, suffix=?, email=?, status=?, student_no=?, course=?, year_level=?, section=?, notes=?, updated_at=NOW()";
$types = "sssssss sssss"; // spaces ignored
$params = [$first,$middle,$last,$suffix,$email,$status,$studno,$course,$year,$sect,$notes];

if ($pw !== '') {
  if (strlen($pw) < 8 || !preg_match('/[A-Z]/',$pw) || !preg_match('/[!@#$%^&*()_+\-=\[\]{}|\\:;\'"<>,.?\/~`]/',$pw)) {
    echo json_encode(['success'=>false,'message'=>'Password must be ≥8 chars, 1 uppercase & 1 special']); exit;
  }
  $hash = password_hash($pw, PASSWORD_BCRYPT);
  $set .= ", password=?";
  $types .= "s";
  $params[] = $hash;
}

$sql = "UPDATE users SET $set WHERE id=? LIMIT 1";
$types .= "i";
$params[] = $id;

$stmt = $conn->prepare($sql);
if (!$stmt) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Prepare failed']); exit; }

$stmt->bind_param($types, ...$params);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Failed to update']); exit; }
echo json_encode(['success'=>true]);
