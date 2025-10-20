<?php
/**
 * 1) Validate email + code
 * 2) Check latest non-expired record
 * 3) Verify code, mark used
 * 4) Issue short-lived reset token (30 minutes) by replacing otp_hash with token hash
 */
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true) ?? [];
$email = trim($in['email'] ?? '');
$code  = preg_replace('/\D/', '', (string)($in['code'] ?? ''));

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($code) !== 6) {
  json_response(['success'=>false,'message'=>'Invalid input.'], 422);
}

try {
  $stmt = $pdo->prepare('
    SELECT id, otp_hash, expires_at, used
      FROM password_resets
     WHERE email = ?
     ORDER BY id DESC
     LIMIT 1
  ');
  $stmt->execute([$email]);
  $row = $stmt->fetch();

  if (!$row) {
    json_response(['success'=>false,'message'=>'No active code for this email.'], 404);
  }
  if ((int)$row['used'] === 1) {
    json_response(['success'=>false,'message'=>'Code already used.'], 400);
  }
  if (strtotime($row['expires_at']) < time()) {
    json_response(['success'=>false,'message'=>'Code expired.'], 400);
  }
  if (!password_verify($code, $row['otp_hash'])) {
    json_response(['success'=>false,'message'=>'Incorrect code.'], 400);
  }

  // Mark used
  $pdo->prepare('UPDATE password_resets SET used=1 WHERE id=?')->execute([$row['id']]);

  // Create a short-lived token (store a hash so we can validate on reset page)
  $token = bin2hex(random_bytes(32));
  $tokenHash = password_hash($token, PASSWORD_BCRYPT);

  $pdo->prepare('
    UPDATE password_resets
       SET otp_hash = ?, expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
     WHERE id = ?
  ')->execute([$tokenHash, $row['id']]);

  json_response(['success'=>true,'token'=>$token]);

} catch (Throwable $e) {
  json_response(['success'=>false,'message'=>$e->getMessage()], 500);
}
