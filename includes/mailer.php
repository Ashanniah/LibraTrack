<?php
/**
 * PHPMailer Wrapper
 * Sends emails using SMTP settings from database
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/settings.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email using PHPMailer
 * 
 * @param PDO $pdo Database connection
 * @param string $toEmail Recipient email address
 * @param string $toName Recipient name
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param string|null $textBody Plain text body (optional, auto-generated if null)
 * @return array ['success' => bool, 'error' => string|null]
 */
function send_mail(PDO $pdo, string $toEmail, string $toName, string $subject, string $htmlBody, ?string $textBody = null): array {
    try {
        // Get SMTP settings
        $smtpSettings = get_settings($pdo, [
            'smtp_host',
            'smtp_port',
            'smtp_user',
            'smtp_pass',
            'smtp_secure',
            'smtp_from',
            'smtp_from_name'
        ]);
        
        if (empty($smtpSettings['smtp_host']) || empty($smtpSettings['smtp_user']) || empty($smtpSettings['smtp_pass'])) {
            return [
                'success' => false,
                'error' => 'SMTP settings not configured'
            ];
        }
        
        // Create PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Configure SMTP
        $mail->isSMTP();
        $mail->Host = $smtpSettings['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpSettings['smtp_user'];
        $mail->Password = $smtpSettings['smtp_pass'];
        $mail->Port = (int)($smtpSettings['smtp_port'] ?? 587);
        
        // Set encryption
        $encryption = strtolower($smtpSettings['smtp_secure'] ?? 'tls');
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'none') {
            $mail->SMTPSecure = '';
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        // Set timeout
        $mail->Timeout = 30;
        
        // Set from address
        $fromEmail = $smtpSettings['smtp_from'] ?? $smtpSettings['smtp_user'];
        $fromName = $smtpSettings['smtp_from_name'] ?? 'LibraTrack';
        $mail->setFrom($fromEmail, $fromName);
        
        // Set recipient
        $mail->addAddress($toEmail, $toName);
        
        // Set content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody ?? strip_tags($htmlBody);
        
        // Send email
        $mail->send();
        
        return [
            'success' => true,
            'error' => null
        ];
    } catch (Exception $e) {
        // Do NOT expose credentials in error messages
        $errorMsg = $e->getMessage();
        
        // Sanitize error message
        if (strpos($errorMsg, $smtpSettings['smtp_user'] ?? '') !== false) {
            $errorMsg = str_replace($smtpSettings['smtp_user'], '[REDACTED]', $errorMsg);
        }
        if (strpos($errorMsg, $smtpSettings['smtp_pass'] ?? '') !== false) {
            $errorMsg = str_replace($smtpSettings['smtp_pass'], '[REDACTED]', $errorMsg);
        }
        
        return [
            'success' => false,
            'error' => substr($errorMsg, 0, 500) // Limit error length
        ];
    }
}




