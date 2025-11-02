<?php
// backend/update-user.php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

// Helper to respond cleanly
function respond(array $data, int $code = 200): void {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  // Require login
  $uid  = (int)($_SESSION['uid'] ?? 0);
  $role = strtolower($_SESSION['role'] ?? '');
  if ($uid <= 0) respond(['success' => false, 'message' => 'Unauthorized'], 401);

  $input = json_decode(file_get_contents('php://input'), true);
  if (!is_array($input)) respond(['success' => false, 'message' => 'Invalid JSON'], 400);

  $id = (int)($input['id'] ?? 0);
  if ($id <= 0) respond(['success' => false, 'message' => 'Missing ID'], 400);

  // Normalize input
  $first   = trim($input['first_name'] ?? '');
  $middle  = trim($input['middle_name'] ?? '');
  $last    = trim($input['last_name'] ?? '');
  $suffix  = trim($input['suffix'] ?? '');
  $email   = strtolower(trim($input['email'] ?? ''));
  $status  = trim($input['status'] ?? 'active');
  $notes   = trim($input['notes'] ?? '');
  $student_no = trim($input['student_no'] ?? '');
  $course  = trim($input['course'] ?? '');
  $year    = trim($input['year_level'] ?? '');
  $section = trim($input['section'] ?? '');
  $password= trim($input['password'] ?? '');
  $school_id = isset($input['school_id']) && $input['school_id'] !== '' ? (int)$input['school_id'] : null;

  if ($first === '' || $last === '') respond(['success' => false, 'message' => 'Name fields required'], 400);
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(['success' => false, 'message' => 'Invalid email'], 400);

  // Determine librarian’s school (for scoping)
  $librSchoolId = 0;
  if ($role === 'librarian') {
    $stmt = $conn->prepare("SELECT school_id FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stmt->bind_result($librSchoolId);
    $stmt->fetch();
    $stmt->close();
    if ($librSchoolId <= 0) respond(['success'=>false,'message'=>'No school assigned to librarian.'],409);
  }

  // Ensure record exists
  $stmt = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $old = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$old) respond(['success'=>false,'message'=>'User not found'],404);

  // Librarians may only update their own students
  if ($role === 'librarian') {
    if (strtolower($old['role']) !== 'student' || (int)$old['school_id'] !== (int)$librSchoolId) {
      respond(['success'=>false,'message'=>'You may only edit your own students.'],403);
    }
    $school_id = $librSchoolId;
  }

  // Duplicate check (same email or student_no)
  $stmt = $conn->prepare("
    SELECT id FROM users
    WHERE (email=? OR student_no=?) AND id<>?
    LIMIT 1
  ");
  $stmt->bind_param('ssi', $email, $student_no, $id);
  $stmt->execute();
  if ($stmt->get_result()->num_rows > 0) {
    respond(['success'=>false,'message'=>'Email or Student No. already exists.'],409);
  }
  $stmt->close();

  // Build SQL dynamically
  $fields = [
    'first_name' => $first,
    'middle_name'=> $middle,
    'last_name'  => $last,
    'suffix'     => $suffix,
    'email'      => $email,
    'status'     => $status,
    'notes'      => $notes,
    'student_no' => $student_no,
    'course'     => $course,
    'year_level' => $year,
    'section'    => $section
  ];
  if ($school_id !== null) $fields['school_id'] = $school_id;
  if ($password !== '')   $fields['password'] = password_hash($password, PASSWORD_BCRYPT);

  $setParts = [];
  $types = '';
  $values = [];
  foreach ($fields as $col => $val) {
    $setParts[] = "$col=?";
    $types .= 's';
    $values[] = $val;
  }
  $types .= 'i';
  $values[] = $id;

  $sql = "UPDATE users SET ".implode(',', $setParts)." WHERE id=?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$values);
  $stmt->execute();

  respond(['success'=>true]);
}
catch (Throwable $e) {
  respond(['success'=>false,'message'=>$e->getMessage()],500);
}
finally {
  if (isset($stmt) && $stmt instanceof mysqli_stmt) $stmt->close();
  if (isset($conn) && $conn instanceof mysqli) $conn->close();
}
