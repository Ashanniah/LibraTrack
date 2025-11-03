<?php
// backend/create-student.php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../php_errors.log');

function jsonError(string $message, int $code = 400, ?string $field = null) {
  while (ob_get_level()) ob_end_clean();
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  $payload = ['success'=>false,'message'=>$message];
  if ($field) $payload['field'] = $field;
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

register_shutdown_function(function() {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR], true)) {
    while (ob_get_level()) ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'message'=>'Fatal error: '.$e['message']], JSON_UNESCAPED_UNICODE);
  }
});

ob_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$role = strtolower($_SESSION['role'] ?? '');
$uid  = (int)($_SESSION['uid'] ?? 0);
if ($uid <= 0 || $role !== 'librarian') jsonError('Unauthorized', 401);

// librarian’s school_id
$schoolId = 0;
if ($stmt = $conn->prepare("SELECT school_id FROM users WHERE id=? LIMIT 1")) {
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $stmt->bind_result($schoolId);
  $stmt->fetch();
  $stmt->close();
}
$schoolId = (int)$schoolId;
if ($schoolId <= 0) jsonError('Librarian must be assigned to a school.', 400);

$data = json_decode(file_get_contents('php://input') ?: '', true) ?: [];

$req = ['first_name','last_name','email','student_no','course','year_level','section','status','password'];
foreach ($req as $f) {
  if (!isset($data[$f]) || trim((string)$data[$f]) === '') {
    jsonError("Missing field: $f", 422, $f);
  }
}

// normalize
$first  = trim((string)$data['first_name']);
$middle = trim((string)($data['middle_name'] ?? ''));
$last   = trim((string)$data['last_name']);
$suffix = trim((string)($data['suffix'] ?? ''));
$email  = strtolower(trim((string)$data['email']));
$studno = trim((string)$data['student_no']);
$course = trim((string)$data['course']);
$year   = trim((string)$data['year_level']);
$sect   = trim((string)$data['section']);
$status = strtolower(trim((string)$data['status']));
$notes  = trim((string)($data['notes'] ?? ''));
$pw     = (string)$data['password'];

// validations
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError('Enter a valid email.', 422, 'email');
if (!preg_match('/^\d{8}$/', $studno)) jsonError('Student number must be exactly 8 digits.', 422, 'student_no');
if (strlen($pw) < 8 || !preg_match('/[A-Z]/',$pw) || !preg_match('/[!@#$%^&*()_+\-=\[\]{}|\\:;\'"<>,.?\/~`]/',$pw)) {
  jsonError('Password must be ≥8 chars, with 1 uppercase & 1 special.', 422, 'password');
}

// unique checks (scope student_no by school)
if ($stmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1")) {
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $stmt->store_result();
  if ($stmt->num_rows > 0) { $stmt->close(); jsonError('This email is already registered.', 409, 'email'); }
  $stmt->close();
}
if ($stmt = $conn->prepare("SELECT id FROM users WHERE student_no=? AND school_id=? LIMIT 1")) {
  $stmt->bind_param("si", $studno, $schoolId);
  $stmt->execute();
  $stmt->store_result();
  if ($stmt->num_rows > 0) { $stmt->close(); jsonError('This Student No. already exists in this school.', 409, 'student_no'); }
  $stmt->close();
}

$hash = password_hash($pw, PASSWORD_BCRYPT);

// insert
ob_end_clean();
$sql = "INSERT INTO users
  (first_name,middle_name,last_name,suffix,email,password,student_no,course,year_level,section,status,notes,role,school_id,created_at)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'student', ?, NOW())";

$stmt = $conn->prepare($sql);
if (!$stmt) jsonError('Prepare failed: '.$conn->error, 500);

if (!$stmt->bind_param(
  "sssssssssssss i",
  $first,$middle,$last,$suffix,$email,$hash,$studno,$course,$year,$sect,$status,$notes,$schoolId
)) {
  jsonError('Bind failed: '.$stmt->error, 500);
}

if (!$stmt->execute()) {
  $err = $stmt->error ?: $conn->error;
  $stmt->close();
  jsonError('Failed to create student: '.$err, 500);
}

$id = $stmt->insert_id;
$stmt->close();
echo json_encode(['success'=>true,'id'=>$id], JSON_UNESCAPED_UNICODE);
