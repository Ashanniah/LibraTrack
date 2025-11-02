<?php
// backend/create-student.php
session_start();
header('Content-Type: application/json');
require_once(__DIR__ . '/db.php');

// Require librarian
$role = strtolower($_SESSION['role'] ?? '');
$uid  = (int)($_SESSION['uid'] ?? 0);
if ($uid <= 0 || $role !== 'librarian') {
  http_response_code(401);
  echo json_encode(['success'=>false,'message'=>'Unauthorized']);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];

$required = ['first_name','last_name','email','student_no','course','year_level','section','status','password'];
foreach ($required as $f) {
  if (!isset($data[$f]) || trim((string)$data[$f]) === '') {
    echo json_encode(['success'=>false,'message'=>"Missing field: $f",'field'=>$f]);
    exit;
  }
}

// Determine librarian's school
$schoolId = 0;
if ($stmt = $conn->prepare("SELECT school_id FROM users WHERE id=? LIMIT 1")) {
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $stmt->bind_result($schoolId);
  $stmt->fetch();
  $stmt->close();
}
$schoolId = (int)$schoolId;
if ($schoolId <= 0) {
  http_response_code(409);
  echo json_encode(['success'=>false,'message'=>'No school assigned to this librarian.']);
  exit;
}

// Normalize inputs
$first  = trim($data['first_name']);
$middle = trim($data['middle_name'] ?? '');
$last   = trim($data['last_name']);
$suffix = trim($data['suffix'] ?? '');
$email  = strtolower(trim($data['email']));
$studno = trim($data['student_no']);
$course = trim($data['course']);
$year   = trim($data['year_level']);
$sect   = trim($data['section']);
$status = trim($data['status']);
$notes  = trim($data['notes'] ?? '');
$pass   = password_hash($data['password'], PASSWORD_BCRYPT);

// Duplicate checks within same school, role=student
if ($stmt = $conn->prepare("SELECT id FROM users WHERE email=? AND school_id=? AND LOWER(role)='student' LIMIT 1")) {
  $stmt->bind_param("si", $email, $schoolId);
  $stmt->execute();
  if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success'=>false,'message'=>'This email is already registered.','field'=>'email']);
    exit;
  }
  $stmt->close();
}

if ($stmt = $conn->prepare("SELECT id FROM users WHERE student_no=? AND school_id=? AND LOWER(role)='student' LIMIT 1")) {
  $stmt->bind_param("si", $studno, $schoolId);
  $stmt->execute();
  if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success'=>false,'message'=>'This Student No. is already registered.','field'=>'student_no']);
    exit;
  }
  $stmt->close();
}

// Insert into users as a student
$stmt = $conn->prepare("
  INSERT INTO users
    (first_name,middle_name,last_name,suffix,email,password,student_no,course,year_level,section,status,notes,role,school_id)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'student', ?)
");
$stmt->bind_param(
  "ssssssssssssi",
  $first,$middle,$last,$suffix,$email,$pass,$studno,$course,$year,$sect,$status,$notes,$schoolId
);

if ($stmt->execute()) {
  echo json_encode(['success'=>true,'id'=>$stmt->insert_id]);
} else {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Failed to create student.']);
}
