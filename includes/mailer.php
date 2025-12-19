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

/**
 * Log email to email_logs table
 * 
 * @param PDO $pdo Database connection
 * @param array $data Email log data
 * @return int|false Log ID or false on failure
 */
function log_email(PDO $pdo, array $data): int|false {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO email_logs 
            (user_id, role, recipient_email, event_type, subject, body_preview, status, error_message, related_entity_type, related_entity_id, created_at, sent_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        $result = $stmt->execute([
            $data['user_id'] ?? null,
            $data['role'] ?? null,
            $data['recipient_email'],
            $data['event_type'] ?? null,
            $data['subject'],
            $data['body_preview'] ?? null,
            $data['status'] ?? 'sent',
            $data['error_message'] ?? null,
            $data['related_entity_type'] ?? null,
            $data['related_entity_id'] ?? null,
            $data['status'] === 'sent' ? date('Y-m-d H:i:s') : null
        ]);
        
        return $result ? (int)$pdo->lastInsertId() : false;
    } catch (Exception $e) {
        error_log("Failed to log email: " . $e->getMessage());
        return false;
    }
}

/**
 * Enhanced send_mail with logging
 * 
 * @param PDO $pdo Database connection
 * @param string $toEmail Recipient email
 * @param string $toName Recipient name
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param string|null $textBody Plain text body
 * @param array $metadata Additional metadata for logging (user_id, role, event_type, related_entity_type, related_entity_id)
 * @return array ['success' => bool, 'error' => string|null, 'log_id' => int|null]
 */
function send_mail_with_log(PDO $pdo, string $toEmail, string $toName, string $subject, string $htmlBody, ?string $textBody = null, array $metadata = []): array {
    $result = send_mail($pdo, $toEmail, $toName, $subject, $htmlBody, $textBody);
    
    // Update log with metadata if provided
    if (isset($metadata['log_id']) && $metadata['log_id']) {
        try {
            $updateStmt = $pdo->prepare("
                UPDATE email_logs 
                SET user_id = ?, role = ?, event_type = ?, related_entity_type = ?, related_entity_id = ?, sent_at = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                $metadata['user_id'] ?? null,
                $metadata['role'] ?? null,
                $metadata['event_type'] ?? null,
                $metadata['related_entity_type'] ?? null,
                $metadata['related_entity_id'] ?? null,
                $result['success'] ? date('Y-m-d H:i:s') : null,
                $metadata['log_id']
            ]);
        } catch (Exception $e) {
            error_log("Failed to update email log: " . $e->getMessage());
        }
    }
    
    return $result;
}




