<?php
session_start();
header('Content-Type: application/json');
require_once("../db.php");

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'librarian') {
  http_response_code(401);
  echo json_encode(["success"=>false,"message"=>"Unauthorized"]);
  exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id = intval($data['id'] ?? 0);
if (!$id) { echo json_encode(["success"=>false,"message"=>"Missing ID"]); exit; }

$stmt = $conn->prepare("SELECT * FROM students WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();
$old = $stmt->get_result()->fetch_assoc();
if(!$old){ echo json_encode(["success"=>false,"message"=>"Not found."]); exit; }

$first  = trim($data['first_name']);
$middle = trim($data['middle_name'] ?? "");
$last   = trim($data['last_name']);
$suffix = trim($data['suffix'] ?? "");
$email  = strtolower(trim($data['email']));
$studno = trim($data['student_no']);
$course = trim($data['course']);
$year   = trim($data['year_level']);
$sect   = trim($data['section']);
$status = trim($data['status']);
$notes  = trim($data['notes'] ?? "");

// Duplicate check (ignore same ID)
$check = $conn->prepare("SELECT id FROM students WHERE (email=? OR student_no=?) AND id<>? ");
$check->bind_param("ssi", $email, $studno, $id);
$check->execute();
if ($check->get_result()->num_rows > 0) {
  echo json_encode(["success"=>false,"message"=>"Email or Student No. already exists."]);
  exit;
}

if (!empty($data['password'])) {
  $pass = password_hash($data['password'], PASSWORD_BCRYPT);
  $stmt = $conn->prepare("UPDATE students SET first_name=?,middle_name=?,last_name=?,suffix=?,email=?,student_no=?,course=?,year_level=?,section=?,status=?,notes=?,password=? WHERE id=?");
  $stmt->bind_param("ssssssssssssi", $first,$middle,$last,$suffix,$email,$studno,$course,$year,$sect,$status,$notes,$pass,$id);
} else {
  $stmt = $conn->prepare("UPDATE students SET first_name=?,middle_name=?,last_name=?,suffix=?,email=?,student_no=?,course=?,year_level=?,section=?,status=?,notes=? WHERE id=?");
  $stmt->bind_param("sssssssssssi", $first,$middle,$last,$suffix,$email,$studno,$course,$year,$sect,$status,$notes,$id);
}

if ($stmt->execute()) echo json_encode(["success"=>true]);
else { http_response_code(500); echo json_encode(["success"=>false,"message"=>"Failed to update."]); }
?>
