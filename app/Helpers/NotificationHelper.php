<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class NotificationHelper
{
    /**
     * Notification type email policies
     * Email-only notification system - only critical/time-sensitive events
     */
    private static function getEmailPolicy(string $type): string
    {
        $policies = [
            // STUDENT notifications
            'BORROW_REQUEST_APPROVED' => 'required',  // Critical
            'BORROW_REQUEST_REJECTED' => 'required',  // Critical
            'DUE_SOON' => 'required',                  // Time-sensitive
            'OVERDUE' => 'required',                   // Critical
            
            // LIBRARIAN notifications
            'LOW_STOCK' => 'required',                // Critical
            'OVERDUE_CRITICAL' => 'required',         // Critical escalation
            
            // ADMIN notifications
            'EMAIL_FAILURE' => 'required',            // System error
            'SMTP_CONFIG_FAILURE' => 'required',      // System error
            
            // Disabled - not critical/time-sensitive
            'BORROW_REQUEST_SUBMITTED' => 'off',
            'NEW_BORROW_REQUEST' => 'off',
            'OVERDUE_SUMMARY' => 'off',
            'SETTINGS_CHANGED' => 'off',
            'SECURITY_ALERT' => 'off',
        ];
        
        return $policies[$type] ?? 'off';
    }

    /**
     * Check if email should be sent
     */
    private static function shouldSendEmail(string $type): bool
    {
        $policy = self::getEmailPolicy($type);
        
        if ($policy === 'required') {
            return true;
        }
        
        if ($policy === 'off') {
            return false;
        }
        
        // For 'optional', check settings
        try {
            $enabled = DB::table('settings')
                ->where('key', 'email_notifications_enabled')
                ->value('value');
            return $enabled === '1';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check spam control (once per day for OVERDUE and LOW_STOCK)
     */
    private static function checkSpamControl(int $recipientUserId, string $type, ?string $entityType, ?int $entityId): bool
    {
        if (!in_array($type, ['OVERDUE', 'LOW_STOCK'], true)) {
            return false; // No spam control needed
        }
        
        try {
            $count = DB::table('notifications')
                ->where('recipient_user_id', $recipientUserId)
                ->where('type', $type)
                ->where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->where('created_at', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 24 HOUR)'))
                ->count();
            
            return $count > 0;
        } catch (\Exception $e) {
            return false; // Fail open
        }
    }

    /**
     * Queue an email notification
     * In-app notifications are created ONLY when emails are successfully sent (in process_email_queue)
     * This function only queues the email - no in-app notification is created here
     */
    public static function queueEmail(
        int $recipientUserId,
        string $type,
        ?string $entityType = null,
        ?int $entityId = null
    ): int {
        try {
            require_once __DIR__ . '/../../includes/email_notifications.php';
            $pdo = DB::connection()->getPdo();
            
            $deliveryId = queue_email($pdo, $recipientUserId, $type, $entityType, $entityId);
            
            if ($deliveryId > 0) {
                error_log("Email queued for user {$recipientUserId}, type {$type}, delivery ID: {$deliveryId}");
            } else {
                error_log("Email skipped for user {$recipientUserId}, type {$type} (policy or spam control)");
            }
            
            return $deliveryId;
        } catch (\Exception $e) {
            error_log("Failed to queue email: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Create a notification (DEPRECATED - use queueEmail instead)
     * Kept for backward compatibility but now only queues emails
     * In-app notifications are created automatically when emails are sent successfully
     */
    public static function create(
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
        // Only queue email - in-app notification will be created when email is sent successfully
        return self::queueEmail($recipientUserId, $type, $entityType, $entityId);
    }

    /**
     * Send notification email
     */
    public static function sendEmail(int $notificationId): bool
    {
        try {
            // Get notification and recipient
            $notification = DB::table('notifications as n')
                ->join('users as u', 'u.id', '=', 'n.recipient_user_id')
                ->where('n.id', $notificationId)
                ->select('n.*', 'u.email', 'u.first_name', 'u.last_name')
                ->first();
            
            if (!$notification || !$notification->email) {
                throw new \Exception("Notification or recipient email not found");
            }
            
            // Get SMTP settings
            $settings = DB::table('settings')
                ->whereIn('key', ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_secure', 'smtp_from', 'smtp_from_name'])
                ->pluck('value', 'key')
                ->toArray();
            
            if (empty($settings['smtp_host'])) {
                throw new \Exception("SMTP settings not configured");
            }
            
            // Create PHPMailer instance
            $mail = new PHPMailer(true);
            
            // Configure SMTP
            $mail->isSMTP();
            $mail->Host = $settings['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $settings['smtp_user'];
            $mail->Password = $settings['smtp_pass'];
            $mail->Port = (int)($settings['smtp_port'] ?? 587);
            
            // Set encryption
            $encryption = strtolower($settings['smtp_secure'] ?? 'tls');
            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            // Set from
            $fromEmail = $settings['smtp_from'] ?? $settings['smtp_user'];
            $fromName = $settings['smtp_from_name'] ?? 'LibraTrack';
            $mail->setFrom($fromEmail, $fromName);
            
            // Set recipient
            $recipientName = trim(($notification->first_name ?? '') . ' ' . ($notification->last_name ?? ''));
            if (empty($recipientName)) {
                $recipientName = $notification->email;
            }
            $mail->addAddress($notification->email, $recipientName);
            
            // Set content
            $mail->isHTML(true);
            $mail->Subject = $notification->title;
            $mail->Body = self::buildEmailTemplate($notification);
            $mail->AltBody = strip_tags($notification->message);
            
            // Send
            $mail->send();
            
            // Update delivery status
            DB::table('notification_deliveries')
                ->where('notification_id', $notificationId)
                ->where('channel', 'email')
                ->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            
            // Log email sent
            try {
                AuditLog::logAction(
                    ['id' => null, 'first_name' => 'System', 'last_name' => '', 'role' => 'admin'],
                    'EMAIL_SENT',
                    'notification',
                    $notificationId,
                    "Email sent for notification type: {$notification->type}"
                );
            } catch (\Exception $e) {
                // Ignore
            }
            
            return true;
        } catch (\Exception $e) {
            // Update delivery status to failed
            try {
                DB::table('notification_deliveries')
                    ->where('notification_id', $notificationId)
                    ->where('channel', 'email')
                    ->update([
                        'status' => 'failed',
                        'error' => substr($e->getMessage(), 0, 500),
                    ]);
            } catch (\Exception $updateError) {
                // Ignore
            }
            
            // Notify admin about email failure (avoid infinite loops)
            try {
                if ($notification->type !== 'EMAIL_FAILURE') {
                    self::notifyEmailFailure($notificationId, $e->getMessage());
                }
            } catch (\Exception $notifyError) {
                // Ignore to avoid infinite loops
            }
            
            throw $e;
        }
    }

    /**
     * Build HTML email template
     */
    private static function buildEmailTemplate($notification): string
    {
        $appName = 'LibraTrack';
        $message = nl2br(htmlspecialchars($notification->message));
        $deepLink = $notification->deep_link ?? '#';
        
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
                    <h3>{$notification->title}</h3>
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
     * Notify admin about email failure
     */
    private static function notifyEmailFailure(int $failedNotificationId, string $error): void
    {
        try {
            // Find admin or librarian
            $admin = DB::table('users')
                ->whereIn('role', ['admin', 'librarian'])
                ->where('status', 'active')
                ->orderByRaw("CASE WHEN role = 'admin' THEN 1 ELSE 2 END")
                ->first();
            
            if ($admin) {
                self::create(
                    $admin->id,
                    strtolower($admin->role),
                    'EMAIL_FAILURE',
                    'Email Delivery Failed',
                    "Failed to send email notification (ID: {$failedNotificationId}). Error: " . substr($error, 0, 200),
                    'notification',
                    $failedNotificationId,
                    null,
                    'off' // Don't send email for email failures
                );
            }
        } catch (\Exception $e) {
            // Fail silently
        }
    }
}

