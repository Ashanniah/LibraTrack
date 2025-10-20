<?php
// backend/register.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

// keep errors out of the JSON payload
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Guard: POST only
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  json_response(['success'=>false,'error'=>'Method not allowed'], 405);
}

// Read JSON body or form-POST fallback
$raw = file_get_contents('php://input') ?: '';
$req = json_decode($raw, true);
if (!is_array($req) || !$req) $req = $_POST;

// Collect & trim
$first   = trim((string)($req['first_name'] ?? ''));
$middle  = trim((string)($req['middle_name'] ?? ''));
$last    = trim((string)($req['last_name'] ?? ''));
$suffix  = trim((string)($req['suffix'] ?? ''));        // optional now
$email   = trim((string)($req['email'] ?? ''));
$pass    = (string)($req['password'] ?? '');
$cpass   = (string)($req['confirm_password'] ?? '');
$reqRole = strtolower(trim((string)($req['role'] ?? '')));

// Validation
// allow letters, space, dots, hyphen, apostrophe (covers common cases)
$namePattern = "/^[\p{L}\p{M}\s\.\-']+$/u";
if ($first === '' || !preg_match($namePattern, $first))  json_response(['success'=>false,'error'=>'Invalid first name','field'=>'first_name'], 422);
if ($last  === '' || !preg_match($namePattern, $last))   json_response(['success'=>false,'error'=>'Invalid last name','field'=>'last_name'], 422);
if ($middle !== '' && !preg_match($namePattern, $middle)) json_response(['success'=>false,'error'=>'Invalid middle name','field'=>'middle_name'], 422);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  json_response(['success'=>false,'error'=>'Invalid email','field'=>'email'], 422);
}

if (strlen($pass) < 8 || !preg_match('/[A-Z]/',$pass) || !preg_match('/[^A-Za-z0-9]/',$pass)) {
  json_response(['success'=>false,'error'=>'Password must be 8+ chars with 1 uppercase & 1 special','field'=>'password'], 422);
}
if ($pass !== $cpass) {
  json_response(['success'=>false,'error'=>'Passwords do not match','field'=>'confirm_password'], 422);
}

// Decide role & status
// - If an ADMIN is creating the user and passed a role, allow 'student'|'librarian'|'admin'.
// - Otherwise force 'student'.
$role = 'student';
$status = 'active';

$isAdmin = (strtolower($_SESSION['role'] ?? '') === 'admin');
if ($isAdmin && in_array($reqRole, ['student','librarian','admin'], true)) {
  $role = $reqRole;
}

// Unique email
$chk = $conn->prepare("SELECT 1 FROM users WHERE email=? LIMIT 1");
$chk->bind_param("s", $email);
$chk->execute(); $chk->store_result();
if ($chk->num_rows > 0) {
  $chk->close();
  json_response(['success'=>false,'error'=>'Email already registered','field'=>'email'], 409);
}
$chk->close();

// Insert
$hash = password_hash($pass, PASSWORD_DEFAULT);

// Convert empty optional fields to NULL
$middleParam = ($middle === '') ? null : $middle;
$suffixParam = ($suffix === '') ? null : $suffix;

$stmt = $conn->prepare(
  "INSERT INTO users (first_name, middle_name, last_name, suffix, email, password, role, status)
   VALUES (?,?,?,?,?,?,?,?)"
);
$stmt->bind_param(
  "ssssssss",
  $first, $middleParam, $last, $suffixParam, $email, $hash, $role, $status
);

if (!$stmt->execute()) {
  $stmt->close();
  json_response(['success'=>false,'error'=>'Insert failed'], 500);
}
$id = $stmt->insert_id;
$stmt->close();


// MySQLi doesn’t auto-translate NULL via bind_param with "s",
// so set NULL using the trick above or use set to null explicitly:
if ($suffix === '') {
  // Fix the previous bind: rebind with NULL via send_long_data not needed; simpler:
  $stmt->close();
  $stmt = $conn->prepare(
    "INSERT INTO users (first_name, middle_name, last_name, suffix, email, password, role, status)
     VALUES (?,?,?,?,?,?,?,?)"
  );
  // use types "ssssssss" and pass null via PHP null; MySQL will accept it for a nullable column
  $null = null;
  $stmt->bind_param("ssssssss", $first, $middle, $last, $null, $email, $hash, $role, $status);
}

if (!$stmt->execute()) {
  $stmt->close();
  json_response(['success'=>false,'error'=>'Insert failed'], 500);
}
$id = $stmt->insert_id;
$stmt->close();

// Optionally: auto-login the user if it’s self-registration (no admin session present)
if (empty($_SESSION['uid'])) {
  session_regenerate_id(true);
  $_SESSION['uid']        = (int)$id;
  $_SESSION['role']       = $role;
  $_SESSION['email']      = $email;
  $_SESSION['first_name'] = $first;
  $_SESSION['last_name']  = $last;
  $_SESSION['logged_in']  = time();
}

// Done
json_response(['success'=>true,'message'=>'Registration successful!','id'=>$id,'role'=>$role]);
