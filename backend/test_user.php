<?php
require_once __DIR__.'/db.php';
$e='admin@libratrack.com';
$stmt=$conn->prepare("SELECT email,password,role,status FROM users WHERE email=? LIMIT 1");
$stmt->bind_param('s',$e);
$stmt->execute();
$u=$stmt->get_result()->fetch_assoc();

header('Content-Type: application/json');
echo json_encode([
  'found'=>(bool)$u,
  'match'=>$u ? password_verify('admin123',$u['password']) : null,
  'status'=>$u['status'] ?? null,
  'role'=>$u['role'] ?? null
]);
