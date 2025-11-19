<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
$u = require_role($conn, ['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_response(['success'=>false,'error'=>'Method not allowed'], 405);
}

$payload = file_get_contents('php://input');
$cfg = json_decode($payload, true);
if (!is_array($cfg)) $cfg = $_POST;

try {
  require_once __DIR__ . '/../vendor/autoload.php';
  $mail = new PHPMailer\PHPMailer\PHPMailer(true);
  $mail->isSMTP();
  $mail->Host = $cfg['smtp_host'] ?? '';
  $mail->SMTPAuth = true;
  $mail->Username = $cfg['smtp_user'] ?? '';
  $mail->Password = $cfg['smtp_pass'] ?? '';
  $mail->SMTPSecure = strtolower((string)($cfg['smtp_secure'] ?? 'tls')) === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = (int)($cfg['smtp_port'] ?? 587);
  $from = $cfg['smtp_from'] ?? $cfg['smtp_user'] ?? '';
  if (!$from) throw new Exception('From email is required');
  $mail->setFrom($from, 'LibraTrack');
  $mail->addAddress($from); // send to self
  $mail->Subject = 'LibraTrack SMTP Test';
  $mail->Body = 'SMTP settings are working.';
  $mail->send();
  json_response(['success'=>true]);
} catch (Throwable $e) {
  json_response(['success'=>false,'error'=>$e->getMessage()], 200);
}



