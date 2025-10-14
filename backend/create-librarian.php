<?php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

// Accept JSON and form POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_response(["success"=>false, "message"=>"Method not allowed"], 405);
}

$raw  = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data) || empty($data)) $data = $_POST;

$first    = trim($data['first_name']  ?? '');
$middle   = trim($data['middle_name'] ?? '');
$last     = trim($data['last_name']   ?? '');
$suffix   = trim($data['suffix']      ?? '');
$email    = trim($data['email']       ?? '');
$password = (string)($data['password'] ?? '');
$status   = trim($data['status']      ?? 'active');
$role     = 'librarian'; // force librarian for this endpoint

// Validate
if ($first === '' || $last === '') {
  json_response(["success"=>false, "message"=>"First and last name are required."], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  json_response(["success"=>false, "message"=>"Enter a valid email."], 422);
}
$pwRule = '/^(?=.*[A-Z])(?=.*[!@#$%^&*()_+\-=\[\]{}|\\:;\'"<>,.?\/~`]).{8,}$/';
if ($password === '' || !preg_match($pwRule, $password)) {
  json_response(["success"=>false, "message"=>"Password must have ≥8 chars, 1 uppercase, 1 special character."], 422);
}

// Check duplicate email
$chk = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
$chk->bind_param("s", $email);
$chk->execute();
$chk->store_result();
if ($chk->num_rows > 0) {
  $chk->close();
  json_response(["success"=>false, "message"=>"Email already exists."], 409);
}
$chk->close();

// Insert user
$hashed = password_hash($password, PASSWORD_BCRYPT);
$stmt = $conn->prepare("INSERT INTO users
  (first_name, middle_name, last_name, suffix, email, password, role, status, created_at)
  VALUES (?,?,?,?,?,?,?,?, NOW())");
if (!$stmt) {
  json_response(["success"=>false, "message"=>"Server error (prepare): ".$conn->error], 500);
}
$stmt->bind_param("ssssssss", $first, $middle, $last, $suffix, $email, $hashed, $role, $status);

if ($stmt->execute()) {
  echo json_encode(["success"=>true, "id"=>$conn->insert_id, "message"=>"Librarian account created successfully!"]);
} else {
  json_response(["success"=>false, "message"=>"Server error: ".$stmt->error], 500);
}
$stmt->close();
$conn->close();
