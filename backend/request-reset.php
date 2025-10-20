<?php
/**
 * 1) Validate email
 * 2) Create 6-digit OTP + store hashed
 * 3) Email the OTP to the user
 * 4) Respond with success/failure
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true) ?? [];
$email = trim($in['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  json_response(['success'=>false,'message'=>'Invalid email.'], 422);
}

try {
  // Optional: ensure email exists in your users table (adjust table/column if different)
  $u = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
  $u->execute([$email]);
  $user = $u->fetch();

  if (!$user) {
    // Don’t reveal existence — pretend success
    json_response(['success'=>true, 'message'=>'If this email is registered, a code has been sent.']);
  }

  // Generate 6-digit code
  $code = (string)random_int(100000, 999999);
  $hash = password_hash($code, PASSWORD_BCRYPT);

  // Expiry: 15 minutes
  $pdo->prepare('INSERT INTO password_resets (email, otp_hash, expires_at, used)
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0)')
      ->execute([$email, $hash]);

  // Send the email
  $subject = 'Your LibraTrack verification code';
  $html = "
    <p>Hello,</p>
    <p>Your verification code is:</p>
    <p style=\"font-size:22px; font-weight:700; letter-spacing:3px;\">{$code}</p>
    <p>This code expires in 15 minutes.</p>
    <p>If you didn’t request this, you can ignore this email.</p>
  ";

  $res = send_mail($email, $subject, $html);
  if (!$res['ok']) {
    // roll back last OTP row to avoid leaving an unusable code
    $pdo->prepare('DELETE FROM password_resets WHERE email=? ORDER BY id DESC LIMIT 1')->execute([$email]);
    json_response(['success'=>false, 'message'=>$res['error']], 500);
  }

  json_response(['success'=>true]);

} catch (Throwable $e) {
  json_response(['success'=>false,'message'=>$e->getMessage()], 500);
}
