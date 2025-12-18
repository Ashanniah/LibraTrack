<?php
/**
 * Email Notification Queue System
 * Handles queuing and processing of email notifications
 */

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/email_templates.php';

/**
 * Check if email should be sent for a notification type
 */
function should_send_email_type(string $type, PDO $pdo): bool {
    // REQUIRED types - always send
    $required = ['OVERDUE', 'LOW_STOCK'];
    if (in_array($type, $required, true)) {
        return true;
    }
    
    // OPTIONAL types - check config flag
    $optional = ['BORROW_REQUEST_APPROVED', 'BORROW_REQUEST_REJECTED', 'DUE_SOON'];
    if (in_array($type, $optional, true)) {
        $enabled = get_setting($pdo, 'email_optional_enabled', '0');
        return $enabled === '1';
    }
    
    // EMAIL_FAILURE - check if enabled
    if ($type === 'EMAIL_FAILURE') {
        $enabled = get_setting($pdo, 'email_failure_notifications', '1');
        return $enabled === '1';
    }
    
    return false;
}

/**
 * Check spam control - prevent duplicate emails
 */
function check_email_spam_control(PDO $pdo, int $recipientUserId, string $type, ?string $entityType, ?int $entityId): bool {
    // Only apply spam control to specific types
    if (!in_array($type, ['OVERDUE', 'LOW_STOCK', 'DUE_SOON'], true)) {
        return false; // No spam control needed
    }
    
    try {
        // Check if email was sent in last 24 hours for same recipient, type, and entity
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as cnt 
            FROM notification_deliveries 
            WHERE recipient_user_id = ? 
            AND type = ? 
            AND entity_type = ? 
            AND entity_id = ? 
            AND status = 'sent'
            AND sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$recipientUserId, $type, $entityType, $entityId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($result && (int)$result['cnt'] > 0);
    } catch (Exception $e) {
        error_log("Spam control check error: " . $e->getMessage());
        return false; // Fail open
    }
}

/**
 * Queue an email notification
 * 
 * @param PDO $pdo Database connection
 * @param int $recipientUserId User ID receiving email
 * @param string $type Notification type
 * @param string|null $entityType Entity type
 * @param int|null $entityId Entity ID
 * @return int Delivery ID or 0 if skipped
 */
function queue_email(PDO $pdo, int $recipientUserId, string $type, ?string $entityType = null, ?int $entityId = null): int {
    try {
        // Check if email should be sent
        if (!should_send_email_type($type, $pdo)) {
            // Insert as skipped
            $stmt = $pdo->prepare("
                INSERT INTO notification_deliveries 
                (recipient_user_id, type, entity_type, entity_id, channel, status, created_at)
                VALUES (?, ?, ?, ?, 'email', 'skipped', NOW())
            ");
            $stmt->execute([$recipientUserId, $type, $entityType, $entityId]);
            return 0; // Skipped
        }
        
        // Check spam control
        if (check_email_spam_control($pdo, $recipientUserId, $type, $entityType, $entityId)) {
            // Insert as skipped due to spam control
            $stmt = $pdo->prepare("
                INSERT INTO notification_deliveries 
                (recipient_user_id, type, entity_type, entity_id, channel, status, error, created_at)
                VALUES (?, ?, ?, ?, 'email', 'skipped', 'Spam control: Email already sent in last 24 hours', NOW())
            ");
            $stmt->execute([$recipientUserId, $type, $entityType, $entityId]);
            return 0; // Skipped
        }
        
        // Queue the email
        $stmt = $pdo->prepare("
            INSERT INTO notification_deliveries 
            (recipient_user_id, type, entity_type, entity_id, channel, status, attempt_count, created_at)
            VALUES (?, ?, ?, ?, 'email', 'queued', 0, NOW())
        ");
        $stmt->execute([$recipientUserId, $type, $entityType, $entityId]);
        
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Error queueing email: " . $e->getMessage());
        return 0;
    }
}

/**
 * Process email queue
 * 
 * @param PDO $pdo Database connection
 * @param int $limit Maximum number of emails to process
 * @return array Statistics ['processed' => int, 'sent' => int, 'failed' => int]
 */
function process_email_queue(PDO $pdo, int $limit = 25): array {
    $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0];
    $failureCounts = []; // Track failures per recipient for EMAIL_FAILURE notifications
    
    try {
        // Get queued emails (oldest first)
        $stmt = $pdo->prepare("
            SELECT d.id, d.recipient_user_id, d.type, d.entity_type, d.entity_id, d.attempt_count
            FROM notification_deliveries d
            WHERE d.status = 'queued'
            AND d.channel = 'email'
            ORDER BY d.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $queued = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($queued as $delivery) {
            $stats['processed']++;
            $deliveryId = (int)$delivery['id'];
            $recipientUserId = (int)$delivery['recipient_user_id'];
            $type = $delivery['type'];
            $entityType = $delivery['entity_type'];
            $entityId = $delivery['entity_id'] ? (int)$delivery['entity_id'] : null;
            $attemptCount = (int)$delivery['attempt_count'];
            
            try {
                // Get recipient info
                $stmt = $pdo->prepare("SELECT id, email, first_name, last_name FROM users WHERE id = ?");
                $stmt->execute([$recipientUserId]);
                $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$recipient || empty($recipient['email'])) {
                    throw new Exception("Recipient not found or no email address");
                }
                
                // Build email template based on type
                $template = build_email_template($pdo, $type, $entityType, $entityId, $recipient);
                
                if (!$template) {
                    throw new Exception("Could not build email template for type: {$type}");
                }
                
                // Send email
                $result = send_mail(
                    $pdo,
                    $recipient['email'],
                    trim(($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? '')),
                    $template['subject'],
                    $template['html']
                );
                
                // Update delivery status
                if ($result['success']) {
                    $stmt = $pdo->prepare("
                        UPDATE notification_deliveries 
                        SET status = 'sent', sent_at = NOW(), attempt_count = attempt_count + 1
                        WHERE id = ?
                    ");
                    $stmt->execute([$deliveryId]);
                    $stats['sent']++;
                    
                    // Create in-app notification ONLY when email is successfully sent
                    try {
                        $notificationData = build_notification_from_email($pdo, $type, $entityType, $entityId, $recipient, $template);
                        if ($notificationData) {
                            $recipientRole = strtolower($recipient['role'] ?? '');
                            
                            if (in_array($recipientRole, ['admin', 'librarian', 'student'], true)) {
                                // Create notification in notifications table
                                $stmt = $pdo->prepare("
                                    INSERT INTO notifications 
                                    (recipient_user_id, recipient_role, type, title, message, entity_type, entity_id, deep_link, is_read, created_at)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
                                ");
                                $stmt->execute([
                                    $recipientUserId,
                                    $recipientRole,
                                    $type,
                                    $notificationData['title'],
                                    $notificationData['message'],
                                    $entityType,
                                    $entityId,
                                    $notificationData['deep_link']
                                ]);
                                
                                $notificationId = $pdo->lastInsertId();
                                
                                // Notification and email delivery are logically linked by:
                                // - Same recipient_user_id, type, entity_type, entity_id
                                // - Notification created immediately after email is sent
                            }
                        }
                    } catch (Exception $notifError) {
                        // Log but don't fail email sending
                        error_log("Failed to create in-app notification for email delivery {$deliveryId}: " . $notifError->getMessage());
                    }
                    
                    // Log success
                    log_email_event($pdo, 'EMAIL_SENT', $recipientUserId, $type, $entityId);
                } else {
                    // Mark as failed
                    $newAttemptCount = $attemptCount + 1;
                    $stmt = $pdo->prepare("
                        UPDATE notification_deliveries 
                        SET status = 'failed', error = ?, attempt_count = ?, last_attempt_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$result['error'], $newAttemptCount, $deliveryId]);
                    $stats['failed']++;
                    
                    // Track failures for EMAIL_FAILURE notification
                    if (!isset($failureCounts[$recipientUserId])) {
                        $failureCounts[$recipientUserId] = 0;
                    }
                    $failureCounts[$recipientUserId]++;
                    
                    // Log failure
                    log_email_event($pdo, 'EMAIL_FAILED', $recipientUserId, $type, $entityId, $result['error']);
                    
                    // If 3+ failures in 15 minutes, notify admin
                    if ($newAttemptCount >= 3) {
                        notify_email_failure_to_admin($pdo, $type, $result['error']);
                    }
                }
            } catch (Exception $e) {
                // Mark as failed
                $newAttemptCount = $attemptCount + 1;
                $stmt = $pdo->prepare("
                    UPDATE notification_deliveries 
                    SET status = 'failed', error = ?, attempt_count = ?, last_attempt_at = NOW()
                    WHERE id = ?
                ");
                $errorMsg = substr($e->getMessage(), 0, 500);
                $stmt->execute([$errorMsg, $newAttemptCount, $deliveryId]);
                $stats['failed']++;
                
                error_log("Email processing error for delivery {$deliveryId}: " . $e->getMessage());
            }
        }
        
        // Check for repeated failures and notify admin
        foreach ($failureCounts as $userId => $count) {
            if ($count >= 3) {
                // Check failures in last 15 minutes
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as cnt 
                    FROM notification_deliveries 
                    WHERE recipient_user_id = ? 
                    AND status = 'failed' 
                    AND last_attempt_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                ");
                $stmt->execute([$userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result && (int)$result['cnt'] >= 3) {
                    notify_email_failure_to_admin($pdo, 'BATCH_FAILURE', "Multiple email failures for user ID {$userId}");
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Error processing email queue: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Build email template based on notification type
 */
function build_email_template(PDO $pdo, string $type, ?string $entityType, ?int $entityId, array $recipient): ?array {
    try {
        switch ($type) {
            case 'OVERDUE':
                if ($entityType === 'loan' && $entityId) {
                    $stmt = $pdo->prepare("
                        SELECT b.title, l.due_at 
                        FROM loans l 
                        INNER JOIN books b ON b.id = l.book_id 
                        WHERE l.id = ?
                    ");
                    $stmt->execute([$entityId]);
                    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($loan) {
                        return tpl_overdue(
                            trim(($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? '')),
                            $loan['title'],
                            $loan['due_at']
                        );
                    }
                }
                break;
                
            case 'LOW_STOCK':
                if ($entityType === 'book' && $entityId) {
                    $threshold = (int)get_setting($pdo, 'low_stock_threshold', 5);
                    $stmt = $pdo->prepare("SELECT title, quantity FROM books WHERE id = ?");
                    $stmt->execute([$entityId]);
                    $book = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($book) {
                        return tpl_low_stock(
                            trim(($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? '')),
                            $book['title'],
                            (int)$book['quantity'],
                            $threshold
                        );
                    }
                }
                break;
                
            case 'DUE_SOON':
                if ($entityType === 'loan' && $entityId) {
                    $stmt = $pdo->prepare("
                        SELECT b.title, l.due_at 
                        FROM loans l 
                        INNER JOIN books b ON b.id = l.book_id 
                        WHERE l.id = ?
                    ");
                    $stmt->execute([$entityId]);
                    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($loan) {
                        return tpl_due_soon(
                            trim(($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? '')),
                            $loan['title'],
                            $loan['due_at']
                        );
                    }
                }
                break;
                
            case 'BORROW_REQUEST_APPROVED':
                if ($entityType === 'loan' && $entityId) {
                    $stmt = $pdo->prepare("
                        SELECT b.title, l.due_at 
                        FROM loans l 
                        INNER JOIN books b ON b.id = l.book_id 
                        WHERE l.id = ?
                    ");
                    $stmt->execute([$entityId]);
                    $loan = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($loan) {
                        return tpl_borrow_approved(
                            trim(($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? '')),
                            $loan['title'],
                            $loan['due_at']
                        );
                    }
                }
                break;
                
            case 'BORROW_REQUEST_REJECTED':
                if ($entityType === 'borrow_request' && $entityId) {
                    $stmt = $pdo->prepare("
                        SELECT b.title, br.rejection_reason 
                        FROM borrow_requests br 
                        INNER JOIN books b ON b.id = br.book_id 
                        WHERE br.id = ?
                    ");
                    $stmt->execute([$entityId]);
                    $request = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($request) {
                        return tpl_borrow_rejected(
                            trim(($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? '')),
                            $request['title'],
                            $request['rejection_reason']
                        );
                    }
                }
                break;
                
            case 'EMAIL_FAILURE':
                return tpl_email_failure(
                    trim(($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? '')),
                    'Email delivery failures detected. Please check SMTP settings.'
                );
        }
    } catch (Exception $e) {
        error_log("Error building email template: " . $e->getMessage());
    }
    
    return null;
}

/**
 * Notify admin about email failures
 */
function notify_email_failure_to_admin(PDO $pdo, string $type, string $error): void {
    try {
        // Find admin
        $stmt = $pdo->prepare("
            SELECT id FROM users 
            WHERE role = 'admin' AND status = 'active' 
            ORDER BY id ASC 
            LIMIT 1
        ");
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            // Queue EMAIL_FAILURE notification
            queue_email($pdo, (int)$admin['id'], 'EMAIL_FAILURE', 'system', null);
        }
    } catch (Exception $e) {
        // Fail silently to avoid infinite loops
        error_log("Error notifying admin about email failure: " . $e->getMessage());
    }
}

/**
 * Log email event to system_logs
 */
function log_email_event(PDO $pdo, string $action, int $userId, string $type, ?int $entityId, ?string $error = null): void {
    try {
        // Check if system_logs table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'system_logs'");
        if ($stmt->rowCount() === 0) {
            return;
        }
        
        $details = "Email notification type: {$type}";
        if ($error) {
            $details .= ". Error: " . substr($error, 0, 200);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO system_logs 
            (actor_user_id, actor_name, actor_role, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
            VALUES (NULL, 'System', 'admin', ?, 'notification', ?, ?, '127.0.0.1', 'Email Queue Processor', NOW())
        ");
        $stmt->execute([$action, $entityId, $details]);
    } catch (Exception $e) {
        // Fail silently
    }
}



