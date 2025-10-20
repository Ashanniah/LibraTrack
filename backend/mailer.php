<?php
require_once __DIR__ . '/config.php';            // your .env/constants loader
require_once __DIR__ . '/vendor/autoload.php';   // <-- Composer autoload

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_mail(string $to, string $subject, string $html): array {
  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;  // smtp.gmail.com
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;  // your Gmail address
    $mail->Password   = SMTP_PASS;  // 16-char App Password (no spaces)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;  // 587

    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);   // should match SMTP_USER for Gmail
    $mail->addReplyTo(SMTP_FROM, SMTP_FROM_NAME);
    $mail->addAddress($to);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $html;
    $mail->AltBody = strip_tags($html);

    $mail->send();
    return ['ok' => true];
  } catch (Exception $e) {
    return ['ok' => false, 'error' => 'Email error: ' . $mail->ErrorInfo];
  }
}
