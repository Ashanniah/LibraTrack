<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

// keep raw errors out of the response; log instead
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Guard
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Read JSON body or form-POST
$raw = file_get_contents('php://input') ?: '';
$req = json_decode($raw, true);
if (!is_array($req) || !$req) $req = $_POST;

// Collect
$email = trim((string)($req['email'] ?? ''));
$pass  = (string)($req['password'] ?? '');

// Validate
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  json_response(['success' => false, 'error' => 'Invalid email', 'field' => 'email'], 422);
}
if ($pass === '') {
  json_response(['success' => false, 'error' => 'Password is required', 'field' => 'password'], 422);
}

// Lookup
$stmt = $conn->prepare(
  "SELECT id, first_name, last_name, email, password, role, status
   FROM users WHERE email = ? LIMIT 1"
);
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
  usleep(250000); // slow down brute force a bit
  json_response(['success' => false, 'error' => 'Invalid email or password', 'field' => 'password'], 401);
}

if (strtolower((string)$user['status']) !== 'active') {
  json_response(['success' => false, 'error' => 'Account is disabled'], 403);
}

if (!password_verify($pass, (string)$user['password'])) {
  usleep(250000);
  json_response(['success' => false, 'error' => 'Invalid email or password', 'field' => 'password'], 401);
}

// Good login → renew session and stash essentials
session_regenerate_id(true);
$_SESSION['uid']        = (int)$user['id'];
$_SESSION['role']       = strtolower((string)$user['role']);
$_SESSION['email']      = (string)$user['email'];
$_SESSION['first_name'] = (string)$user['first_name'];
$_SESSION['last_name']  = (string)$user['last_name'];
$_SESSION['logged_in']  = time();

json_response([
  'success'  => true,
  'redirect' => '/libratrack/dashboard.html',
  'role'     => $_SESSION['role'],
]);
