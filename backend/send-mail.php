<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// If you installed PHPMailer via Composer:
require_once __DIR__ . '/../vendor/autoload.php';

// If you copied the PHPMailer source manually, use these instead:
// require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
// require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
// require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

require_once __DIR__ . '/config.php';

function sendResetCode(string $toEmail, string $code): void {
  $mail = new PHPMailer(true);

  // SMTP
  $mail->isSMTP();
  $mail->Host       = SMTP_HOST;
  $mail->SMTPAuth   = true;
  $mail->Username   = SMTP_USER;
  $mail->Password   = SMTP_PASS;
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = SMTP_PORT;

  // From/To (IMPORTANT: From must be valid & usually = SMTP_USER with Gmail)
  $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
  $mail->addAddress($toEmail);

  // Content
  $mail->isHTML(true);
  $mail->Subject = 'Your LibraTrack verification code';
  $mail->Body    = "
    <p>Your verification code is:</p>
    <p style='font-size:22px;font-weight:700;letter-spacing:4px'>{$code}</p>
    <p>This code expires in 15 minutes.</p>";
  $mail->AltBody = "Your verification code is: {$code}\nThis code expires in 15 minutes.";

  if (!$mail->send()) {
    throw new Exception('Mailer error: ' . $mail->ErrorInfo);
  }
}
