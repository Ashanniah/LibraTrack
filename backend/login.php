<?php
// backend/login.php
declare(strict_types=1);

header('Content-Type: application/json');
ini_set('display_errors','0');
ini_set('log_errors','1');

// --- DB config (edit if yours is different) ---
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'libratrack';

// helpers
function bad(string $msg, ?string $field=null, int $code=400): never {
  http_response_code($code);
  echo json_encode(['success'=>false,'message'=>$msg,'field'=>$field]);
  exit;
}

// method guard
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') bad('Method not allowed', null, 405);

// read JSON (or form fallback)
$raw = file_get_contents('php://input') ?: '';
$req = json_decode($raw, true);
if (!is_array($req) || !$req) $req = $_POST;

$email    = trim((string)($req['email'] ?? ''));
$password = (string)($req['password'] ?? '');

if ($email === '') bad('Email address is required', 'email', 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) bad('Invalid email address', 'email', 422);
if ($password === '') bad('Password is required', 'password', 422);

// connect
$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_errno) bad('Database connection failed', null, 500);

// lookup ANY user by email (admin/librarian/student)
$sql = "SELECT id, first_name, last_name, email, password, role, status 
        FROM users WHERE email=? LIMIT 1";
$stmt = $mysqli->prepare($sql);
if (!$stmt) bad('Server error (prep)', null, 500);
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$row = $res?->fetch_assoc();
$stmt->close();

if (!$row) bad('Account not found', 'email', 401);
if (strtolower((string)$row['status']) !== 'active') bad('Your account is disabled. Please contact admin.', 'status', 403);
if (!password_verify($password, (string)$row['password'])) bad('Incorrect password', 'password', 401);

// start secure session
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
  'httponly' => true,
  'samesite' => 'Lax'
]);
session_start();

$_SESSION['user'] = [
  'id'         => (int)$row['id'],
  'first_name' => (string)$row['first_name'],
  'last_name'  => (string)$row['last_name'],
  'email'      => (string)$row['email'],
  'role'       => strtolower((string)$row['role']),
  'status'     => (string)$row['status'],
  'logged_in'  => time(),
];

echo json_encode([
  'success'  => true,
  'message'  => 'Login successful',
  // single, unified dashboard for every role
  'redirect' => '/libratrack/dashboard.html',
  'role'     => strtolower((string)$row['role'])
]);
