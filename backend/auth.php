<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function current_user(mysqli $conn): ?array {
  if (empty($_SESSION['uid'])) return null;
  $uid = (int)$_SESSION['uid'];
  $stmt = $conn->prepare("SELECT id, first_name, last_name, email, role, status FROM users WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$res || strtolower($res['status']) !== 'active') return null;
  return $res;
}

function require_login(mysqli $conn): array {
  $u = current_user($conn);
  if (!$u) json_response(['ok'=>false,'error'=>'Not authenticated'], 401);
  return $u;
}

function require_role(mysqli $conn, array $roles): array {
  $u = require_login($conn);
  $role = strtolower($u['role']);
  $roles = array_map('strtolower', $roles);
  if (!in_array($role, $roles, true)) {
    json_response(['ok'=>false,'error'=>'Only librarians or admins can perform this action'], 403);
  }
  return $u;
}
