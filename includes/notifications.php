<?php
/**
 * Notification Service for LibraTrack
 * Handles creation and delivery of in-app and email notifications
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Notification type policies - determines if email should be sent
 */
function get_notification_email_policy(string $type): string {
    // 'required' = must send email, 'optional' = send if enabled, 'off' = never send
    $policies = [
        // Student notifications
        'BORROW_REQUEST_SUBMITTED' => 'off',
        'BORROW_REQUEST_APPROVED' => 'optional',
        'BORROW_REQUEST_REJECTED' => 'optional',
        'DUE_SOON' => 'optional',
        'OVERDUE' => 'required',
        
        // Librarian notifications
        'NEW_BORROW_REQUEST' => 'off',
        'LOW_STOCK' => 'required',
        'OVERDUE_SUMMARY' => 'optional',
        'EMAIL_FAILURE' => 'off',
        
        // Admin notifications
        'SETTINGS_CHANGED' => 'optional',
        'SECURITY_ALERT' => 'optional',
        'EMAIL_FAILURE' => 'off',
    ];
    
    return $policies[$type] ?? 'off';
}

/**
 * Check if email should be sent for a notification type
 */
function should_send_email(string $type, ?PDO $pdo = null): bool {
    $policy = get_notification_email_policy($type);
    
    if ($policy === 'required') {
        return true;
    }
    
    if ($policy === 'off') {
        return false;
    }
    
    // For 'optional', check if email notifications are enabled in settings
    if ($policy === 'optional' && $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'email_notifications_enabled' LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return ($result && $result['value'] === '1');
        } catch (Exception $e) {
            // If settings table doesn't exist or error, default to false
            return false;
        }
    }
    
    return false;
}

/**
 * Check for spam/duplicate notifications (once per day rule)
 */
function check_spam_control(PDO $pdo, int $recipientUserId, string $type, ?string $entityType, ?int $entityId): bool {
    try {
        // Check if same notification was sent in last 24 hours
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as cnt 
            FROM notifications 
            WHERE recipient_user_id = ? 
            AND type = ? 
            AND entity_type = ? 
            AND entity_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$recipientUserId, $type, $entityType, $entityId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($result && (int)$result['cnt'] > 0);
    } catch (Exception $e) {
        // On error, allow notification (fail open)
        return false;
    }
}

/**
 * Create a notification (in-app + optionally email)
 * 
 * @param PDO $pdo Database connection
 * @param int $recipientUserId User ID receiving notification
 * @param string $recipientRole Role of recipient (admin, librarian, student)
 * @param string $type Notification type
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string|null $entityType Entity type (e.g., 'book', 'loan', 'borrow_request')
 * @param int|null $entityId Entity ID
 * @param string|null $deepLink Deep link URL
 * @param string $emailPolicy Override email policy (auto, required, optional, off)
 * @return int Notification ID
 */
function create_notification(
    PDO $pdo,
    int $recipientUserId,
    string $recipientRole,
    string $type,
    string $title,
    string $message,
    ?string $entityType = null,
    ?int $entityId = null,
    ?string $deepLink = null,
    string $emailPolicy = 'auto'
): int {
    // Validate recipient role
    $recipientRole = strtolower($recipientRole);
    if (!in_array($recipientRole, ['admin', 'librarian', 'student'], true)) {
        throw new Exception("Invalid recipient role: {$recipientRole}");
    }
    
    // Spam control for OVERDUE and LOW_STOCK
    if (in_array($type, ['OVERDUE', 'LOW_STOCK'], true)) {
        if (check_spam_control($pdo, $recipientUserId, $type, $entityType, $entityId)) {
            // Skip creating duplicate notification
            throw new Exception("Spam control: Notification already sent in last 24 hours");
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        // Insert notification
        $stmt = $pdo->prepare("
            INSERT INTO notifications 
            (recipient_user_id, recipient_role, type, title, message, entity_type, entity_id, deep_link, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([
            $recipientUserId,
            $recipientRole,
            $type,
            $title,
            $message,
            $entityType,
            $entityId,
            $deepLink
        ]);
        
        $notificationId = (int)$pdo->lastInsertId();
        
        // Create in-app delivery record (always sent)
        $stmt = $pdo->prepare("
            INSERT INTO notification_deliveries (notification_id, channel, status, created_at)
            VALUES (?, 'in_app', 'sent', NOW())
        ");
        $stmt->execute([$notificationId]);
        
        // Determine if email should be sent
        $sendEmail = false;
        if ($emailPolicy === 'auto') {
            $sendEmail = should_send_email($type, $pdo);
        } elseif ($emailPolicy === 'required') {
            $sendEmail = true;
        } elseif ($emailPolicy === 'optional') {
            $sendEmail = should_send_email($type, $pdo);
        } // 'off' means don't send
        
        if ($sendEmail) {
            // Create email delivery record (queued)
            $stmt = $pdo->prepare("
                INSERT INTO notification_deliveries (notification_id, channel, status, created_at)
                VALUES (?, 'email', 'queued', NOW())
            ");
            $stmt->execute([$notificationId]);
            
            // Try to send email immediately (non-blocking)
            try {
                send_notification_email($pdo, $notificationId);
            } catch (Exception $e) {
                // Log error but don't fail the notification creation
                error_log("Failed to send notification email: " . $e->getMessage());
            }
        } else {
            // Mark email as skipped
            $stmt = $pdo->prepare("
                INSERT INTO notification_deliveries (notification_id, channel, status, created_at)
                VALUES (?, 'email', 'skipped', NOW())
            ");
            $stmt->execute([$notificationId]);
        }
        
        $pdo->commit();
        
        // Log notification creation
        try {
            log_notification_action($pdo, 'NOTIF_CREATED', $notificationId, $type);
        } catch (Exception $e) {
            // Ignore logging errors
        }
        
        return $notificationId;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Send notification email via PHPMailer
 * 
 * @param PDO $pdo Database connection
 * @param int $notificationId Notification ID
 * @return bool Success status
 */
function send_notification_email(PDO $pdo, int $notificationId): bool {
    try {
        // Get notification and recipient details
        $stmt = $pdo->prepare("
            SELECT n.*, u.email, u.first_name, u.last_name
            FROM notifications n
            INNER JOIN users u ON u.id = n.recipient_user_id
            WHERE n.id = ?
        ");
        $stmt->execute([$notificationId]);
        $notification = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$notification || !$notification['email']) {
            throw new Exception("Notification or recipient email not found");
        }
        
        // Get SMTP settings from settings table
        $smtpSettings = get_smtp_settings($pdo);
        
        if (!$smtpSettings || empty($smtpSettings['smtp_host'])) {
            throw new Exception("SMTP settings not configured");
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
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        // Set from address
        $fromEmail = $smtpSettings['smtp_from'] ?? $smtpSettings['smtp_user'];
        $fromName = $smtpSettings['smtp_from_name'] ?? 'LibraTrack';
        $mail->setFrom($fromEmail, $fromName);
        
        // Set recipient
        $recipientName = trim(($notification['first_name'] ?? '') . ' ' . ($notification['last_name'] ?? ''));
        if (empty($recipientName)) {
            $recipientName = $notification['email'];
        }
        $mail->addAddress($notification['email'], $recipientName);
        
        // Set content
        $mail->isHTML(true);
        $mail->Subject = $notification['title'];
        
        // Build HTML email body
        $htmlBody = build_email_template($notification);
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($notification['message']);
        
        // Send email
        $mail->send();
        
        // Update delivery status
        $stmt = $pdo->prepare("
            UPDATE notification_deliveries 
            SET status = 'sent', sent_at = NOW() 
            WHERE notification_id = ? AND channel = 'email'
        ");
        $stmt->execute([$notificationId]);
        
        // Log email sent
        try {
            log_notification_action($pdo, 'EMAIL_SENT', $notificationId, $notification['type']);
        } catch (Exception $e) {
            // Ignore logging errors
        }
        
        return true;
    } catch (Exception $e) {
        // Update delivery status to failed
        try {
            $errorMsg = substr($e->getMessage(), 0, 500); // Limit error message length
            $stmt = $pdo->prepare("
                UPDATE notification_deliveries 
                SET status = 'failed', error = ? 
                WHERE notification_id = ? AND channel = 'email'
            ");
            $stmt->execute([$errorMsg, $notificationId]);
        } catch (Exception $updateError) {
            // Ignore update errors
        }
        
        // Create EMAIL_FAILURE notification for admin/librarian (avoid infinite loops)
        try {
            if (!in_array($notification['type'] ?? '', ['EMAIL_FAILURE'], true)) {
                notify_email_failure($pdo, $notificationId, $e->getMessage());
            }
        } catch (Exception $notifyError) {
            // Ignore notification errors to avoid infinite loops
        }
        
        throw $e;
    }
}

/**
 * Get SMTP settings from database
 */
function get_smtp_settings(PDO $pdo): ?array {
    try {
        $stmt = $pdo->prepare("
            SELECT `key`, value 
            FROM settings 
            WHERE `key` IN ('smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_secure', 'smtp_from', 'smtp_from_name')
        ");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['key']] = $row['value'];
        }
        
        return $settings;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Build HTML email template
 */
function build_email_template(array $notification): string {
    $appName = 'LibraTrack';
    $message = nl2br(htmlspecialchars($notification['message']));
    $deepLink = $notification['deep_link'] ?? '#';
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #111827; color: #fff; padding: 20px; text-align: center; }
            .content { background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; }
            .button { display: inline-block; padding: 12px 24px; background: #a68604; color: #fff; text-decoration: none; border-radius: 6px; margin-top: 15px; }
            .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>{$appName}</h2>
            </div>
            <div class='content'>
                <h3>{$notification['title']}</h3>
                <p>{$message}</p>
                " . ($deepLink !== '#' ? "<a href='{$deepLink}' class='button'>View Details</a>" : "") . "
            </div>
            <div class='footer'>
                <p>This is an automated notification from {$appName}</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Notify admin/librarian about email failure
 */
function notify_email_failure(PDO $pdo, int $failedNotificationId, string $error): void {
    try {
        // Get the failed notification to find recipient
        $stmt = $pdo->prepare("SELECT recipient_user_id, recipient_role FROM notifications WHERE id = ?");
        $stmt->execute([$failedNotificationId]);
        $failedNotif = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$failedNotif) return;
        
        // Find admin or librarian to notify
        $stmt = $pdo->prepare("
            SELECT id, role FROM users 
            WHERE role IN ('admin', 'librarian') AND status = 'active'
            ORDER BY CASE WHEN role = 'admin' THEN 1 ELSE 2 END
            LIMIT 1
        ");
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            create_notification(
                $pdo,
                (int)$admin['id'],
                strtolower($admin['role']),
                'EMAIL_FAILURE',
                'Email Delivery Failed',
                "Failed to send email notification (ID: {$failedNotificationId}). Error: " . substr($error, 0, 200),
                'notification',
                $failedNotificationId,
                null,
                'off' // Don't send email for email failure notifications
            );
        }
    } catch (Exception $e) {
        // Fail silently to avoid infinite loops
    }
}

/**
 * Log notification action to system_logs
 */
function log_notification_action(PDO $pdo, string $action, int $notificationId, string $type): void {
    try {
        // Check if system_logs table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'system_logs'");
        if ($stmt->rowCount() === 0) {
            return; // Table doesn't exist, skip logging
        }
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ipAddress = trim($ips[0]);
        }
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs 
            (actor_user_id, actor_name, actor_role, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
            VALUES (NULL, 'System', 'admin', ?, 'notification', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $action,
            $notificationId,
            "Notification type: {$type}",
            $ipAddress,
            $userAgent
        ]);
    } catch (Exception $e) {
        // Fail silently
    }
}




