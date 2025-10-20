<?php
// backend/register.php
declare(strict_types=1);

require_once __DIR__ . '/db.php'; // db.php already does the optional config include
header('Content-Type: application/json');

// keep errors out of the JSON payload (but log them)
ini_set('display_errors', '0');
ini_set('log_errors', '1');

function jexit(array $data, int $code = 200): void {
  http_response_code($code);
  echo json_encode($data);
  exit;
}

// POST only
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  jexit(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Read JSON body or form-POST fallback
$raw = file_get_contents('php://input') ?: '';
$req = json_decode($raw, true);
if (!is_array($req) || !$req) $req = $_POST;

// Collect & trim
$first   = trim((string)($req['first_name'] ?? ''));
$middle  = trim((string)($req['middle_name'] ?? ''));
$last    = trim((string)($req['last_name'] ?? ''));
$suffix  = trim((string)($req['suffix'] ?? ''));    // optional
$email   = (string)($req['email'] ?? '');
$pass    = (string)($req['password'] ?? '');
$cpass   = (string)($req['confirm_password'] ?? '');
$reqRole = strtolower(trim((string)($req['role'] ?? '')));

// ---- Minimal email normalization (handles invisible chars & IDN safely on PHP 8.1+) ----
$email = trim($email);
// strip zero-width/invisible spaces and normal whitespace
$email = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}\u00A0\s]+/u', '', $email);
if (strpos($email, '@') !== false) {
  [$local, $domain] = explode('@', $email, 2);
  if ($domain !== '' && function_exists('idn_to_ascii')) {
    // Use IDNA_DEFAULT only (INTL_IDNA_VARIANT_UTS46 is removed in PHP 8.1+)
    $asciiDomain = idn_to_ascii($domain, IDNA_DEFAULT);
    if ($asciiDomain) {
      $email = $local . '@' . $asciiDomain;
    }
  }
}

// Validation
$namePattern = "/^[\p{L}\p{M}\s\.\-']+$/u";
if ($first === '' || !preg_match($namePattern, $first))  jexit(['success'=>false,'message'=>'Invalid first name','field'=>'first_name'], 422);
if ($last  === '' || !preg_match($namePattern, $last))   jexit(['success'=>false,'message'=>'Invalid last name','field'=>'last_name'], 422);
if ($middle !== '' && !preg_match($namePattern, $middle)) jexit(['success'=>false,'message'=>'Invalid middle name','field'=>'middle_name'], 422);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  jexit(['success'=>false,'message'=>'Invalid email','field'=>'email'], 422);
}

if (strlen($pass) < 8 || !preg_match('/[A-Z]/',$pass) || !preg_match('/[^A-Za-z0-9]/',$pass)) {
  jexit(['success'=>false,'message'=>'Password must be 8+ chars with 1 uppercase & 1 special','field'=>'password'], 422);
}
if ($pass !== $cpass) {
  jexit(['success'=>false,'message'=>'Passwords do not match','field'=>'confirm_password'], 422);
}

// Role & status
$role = 'student';
$status = 'active';
$isAdmin = (strtolower($_SESSION['role'] ?? '') === 'admin');
if ($isAdmin && in_array($reqRole, ['student','librarian','admin'], true)) {
  $role = $reqRole;
}

// Unique email
$chk = $conn->prepare("SELECT 1 FROM users WHERE email=? LIMIT 1");
$chk->bind_param("s", $email);
$chk->execute();
$chk->store_result();
if ($chk->num_rows > 0) {
  $chk->close();
  jexit(['success'=>false,'message'=>'Email already registered','field'=>'email'], 409);
}
$chk->close();

// Insert (now includes created_at)
$hash = password_hash($pass, PASSWORD_DEFAULT);
$middleParam = ($middle === '') ? null : $middle;
$suffixParam = ($suffix === '') ? null : $suffix;

$stmt = $conn->prepare(
  "INSERT INTO users
     (first_name, middle_name, last_name, suffix, email, password, role, status, created_at)
   VALUES
     (?,?,?,?,?,?,?, ?, NOW())"
);
if (!$stmt) {
  jexit(['success'=>false,'message'=>'Server error (prepare): '.$conn->error], 500);
}
$stmt->bind_param("ssssssss", $first, $middleParam, $last, $suffixParam, $email, $hash, $role, $status);

if (!$stmt->execute()) {
  $msg = $stmt->error ?: 'Insert failed';
  $stmt->close();
  jexit(['success'=>false,'message'=>$msg], 500);
}
$id = $stmt->insert_id;
$stmt->close();

// Auto-login on self-registration
if (empty($_SESSION['uid'])) {
  session_regenerate_id(true);
  $_SESSION['uid']        = (int)$id;
  $_SESSION['role']       = $role;
  $_SESSION['email']      = $email;
  $_SESSION['first_name'] = $first;
  $_SESSION['last_name']  = $last;
  $_SESSION['logged_in']  = time();
}

jexit(['success'=>true,'message'=>'Registration successful!','id'=>$id,'role'=>$role]);
