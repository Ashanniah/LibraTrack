<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  json_response(['success'=>false,'error'=>'Method not allowed'], 405);
}

$raw = file_get_contents('php://input') ?: '';
$req = json_decode($raw, true);
if (!is_array($req) || !$req) $req = $_POST;

$email = trim((string)($req['email'] ?? ''));
$pass  = (string)($req['password'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  json_response(['success'=>false,'error'=>'Invalid email','field'=>'email'], 422);
}
if ($pass === '') {
  json_response(['success'=>false,'error'=>'Password is required','field'=>'password'], 422);
}

$stmt = $conn->prepare(
  "SELECT id, first_name, last_name, email, password, role, status
   FROM users WHERE email=? LIMIT 1"
);
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
  usleep(250000);
  json_response(['success'=>false,'error'=>'Invalid email or password','field'=>'password'], 401);
}
if (strtolower((string)$user['status']) !== 'active') {
  json_response(['success'=>false,'error'=>'Account is disabled'], 403);
}

$stored = trim((string)$user['password']);
$ok     = false;
$info   = password_get_info($stored);

if ($stored !== '' && $info['algo'] !== 0) {
  $ok = password_verify($pass, $stored);
  if ($ok && password_needs_rehash($stored, PASSWORD_DEFAULT)) {
    $new = password_hash($pass, PASSWORD_DEFAULT);
    $u = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $uid = (int)$user['id'];
    $u->bind_param('si', $new, $uid);
    $u->execute(); $u->close();
  }
} elseif ($stored !== '' && hash_equals($stored, $pass)) {
  $ok = true;
  $new = password_hash($pass, PASSWORD_DEFAULT);
  $u = $conn->prepare("UPDATE users SET password=? WHERE id=?");
  $uid = (int)$user['id'];
  $u->bind_param('si', $new, $uid);
  $u->execute(); $u->close();
} elseif ($stored !== '' && hash_equals($stored, md5($pass))) {
  $ok = true;
  $new = password_hash($pass, PASSWORD_DEFAULT);
  $u = $conn->prepare("UPDATE users SET password=? WHERE id=?");
  $uid = (int)$user['id'];
  $u->bind_param('si', $new, $uid);
  $u->execute(); $u->close();
}

if (!$ok) {
  usleep(250000);
  json_response(['success'=>false,'error'=>'Invalid email or password','field'=>'password'], 401);
}

// set session then regenerate
$_SESSION['uid']        = (int)$user['id'];
$_SESSION['role']       = strtolower((string)$user['role']);
$_SESSION['email']      = (string)$user['email'];
$_SESSION['first_name'] = (string)$user['first_name'];
$_SESSION['last_name']  = (string)$user['last_name'];
$_SESSION['logged_in']  = time();
session_regenerate_id(true);

json_response([
  'success'    => true,
  'redirect'   => '/libratrack/dashboard.html',
  'role'       => $_SESSION['role'],
  'user'       => [
    'id'         => $_SESSION['uid'],
    'first_name' => $_SESSION['first_name'],
    'last_name'  => $_SESSION['last_name'],
    'email'      => $_SESSION['email'],
    'role'       => $_SESSION['role'],
  ],
  'session_id' => session_id(),
]);
