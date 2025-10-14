<?php
require 'db.php';
require 'vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');
$data = json_decode(file_get_contents("php://input"), true);

$email = $data['email'] ?? '';
if (!$email) { echo json_encode(['ok'=>false,'error'=>'Email required']); exit; }

$stmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
$stmt->execute([$email]);
$user = $stmt->fetch();
if (!$user) { echo json_encode(['ok'=>false,'error'=>'Email not found']); exit; }

$otp = rand(100000,999999);
$expires = date("Y-m-d H:i:s", time()+600); // 10 mins

$pdo->prepare("UPDATE users SET reset_code=?, reset_expires=? WHERE id=?")
    ->execute([$otp, $expires, $user['id']]);

// send via Gmail SMTP
$mail = new PHPMailer(true);
try {
  $mail->isSMTP();
  $mail->Host = 'smtp.gmail.com';
  $mail->SMTPAuth = true;
  $mail->Username = 'yourgmail@gmail.com';
  $mail->Password = 'app-password'; // use App Password
  $mail->SMTPSecure = 'tls';
  $mail->Port = 587;

  $mail->setFrom('yourgmail@gmail.com','LibraTrack');
  $mail->addAddress($email);
  $mail->isHTML(true);
  $mail->Subject = "Your LibraTrack reset code";
  $mail->Body = "Your OTP is <b>$otp</b>. It expires in 10 minutes.";

  $mail->send();
  echo json_encode(['ok'=>true]);
} catch(Exception $e){
  echo json_encode(['ok'=>false,'error'=>$mail->ErrorInfo]);
}
