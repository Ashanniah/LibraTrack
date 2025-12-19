<?php
/**
 * Email Templates
 * Returns formatted email subject and HTML body for different notification types
 */

/**
 * Overdue book notification template
 */
function tpl_overdue(string $studentName, string $bookTitle, string $dueDate): array {
    $subject = "Book Overdue: {$bookTitle}";
    $dueDateFormatted = date('M d, Y', strtotime($dueDate));
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc2626; color: #fff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #fff; padding: 30px; border: 1px solid #e5e7eb; }
            .alert-box { background: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0; }
            .button { display: inline-block; padding: 12px 24px; background: #dc2626; color: #fff; text-decoration: none; border-radius: 6px; margin-top: 15px; }
            .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #f9fafb; border-radius: 0 0 8px 8px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>LibraTrack</h2>
            </div>
            <div class='content'>
                <h3>Book Overdue Notice</h3>
                <p>Dear {$studentName},</p>
                <div class='alert-box'>
                    <strong>Action Required:</strong> The book <strong>{$bookTitle}</strong> was due on <strong>{$dueDateFormatted}</strong> and is now overdue.
                </div>
                <p>Please return this book to the library as soon as possible to avoid any penalties.</p>
                <a href='#' class='button'>View My Loans</a>
            </div>
            <div class='footer'>
                <p>This is an automated message from LibraTrack Library Management System.</p>
                <p>Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return ['subject' => $subject, 'html' => $html];
}

/**
 * Low stock alert template
 */
function tpl_low_stock(string $staffName, string $bookTitle, int $qty, int $threshold): array {
    $subject = "Low Stock Alert: {$bookTitle}";
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #f59e0b; color: #fff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #fff; padding: 30px; border: 1px solid #e5e7eb; }
            .alert-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
            .button { display: inline-block; padding: 12px 24px; background: #f59e0b; color: #fff; text-decoration: none; border-radius: 6px; margin-top: 15px; }
            .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #f9fafb; border-radius: 0 0 8px 8px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>LibraTrack</h2>
            </div>
            <div class='content'>
                <h3>Low Stock Alert</h3>
                <p>Dear {$staffName},</p>
                <div class='alert-box'>
                    <strong>Inventory Alert:</strong> The book <strong>{$bookTitle}</strong> has reached the low-stock threshold.
                    <br><br>
                    <strong>Current Quantity:</strong> {$qty} copies<br>
                    <strong>Threshold:</strong> {$threshold} copies
                </div>
                <p>Please consider restocking this book to ensure availability for students.</p>
                <a href='#' class='button'>View Book Inventory</a>
            </div>
            <div class='footer'>
                <p>This is an automated message from LibraTrack Library Management System.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return ['subject' => $subject, 'html' => $html];
}

/**
 * Due soon reminder template
 */
function tpl_due_soon(string $studentName, string $bookTitle, string $dueDate): array {
    $subject = "Reminder: Book Due Soon - {$bookTitle}";
    $dueDateFormatted = date('M d, Y', strtotime($dueDate));
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #f59e0b; color: #fff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #fff; padding: 30px; border: 1px solid #e5e7eb; }
            .info-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
            .button { display: inline-block; padding: 12px 24px; background: #f59e0b; color: #fff; text-decoration: none; border-radius: 6px; margin-top: 15px; }
            .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #f9fafb; border-radius: 0 0 8px 8px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>LibraTrack</h2>
            </div>
            <div class='content'>
                <h3>Book Due Soon Reminder</h3>
                <p>Dear {$studentName},</p>
                <div class='info-box'>
                    <strong>Reminder:</strong> The book <strong>{$bookTitle}</strong> is due on <strong>{$dueDateFormatted}</strong>.
                </div>
                <p>Please return this book on or before the due date to avoid any penalties.</p>
                <a href='#' class='button'>View My Loans</a>
            </div>
            <div class='footer'>
                <p>This is an automated message from LibraTrack Library Management System.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return ['subject' => $subject, 'html' => $html];
}

/**
 * Borrow request approved template
 */
function tpl_borrow_approved(string $studentName, string $bookTitle, string $dueDate): array {
    $subject = "Borrow Request Approved: {$bookTitle}";
    $dueDateFormatted = date('M d, Y', strtotime($dueDate));
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #10b981; color: #fff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #fff; padding: 30px; border: 1px solid #e5e7eb; }
            .success-box { background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0; }
            .button { display: inline-block; padding: 12px 24px; background: #10b981; color: #fff; text-decoration: none; border-radius: 6px; margin-top: 15px; }
            .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #f9fafb; border-radius: 0 0 8px 8px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>LibraTrack</h2>
            </div>
            <div class='content'>
                <h3>Borrow Request Approved</h3>
                <p>Dear {$studentName},</p>
                <div class='success-box'>
                    <strong>Good News!</strong> Your request to borrow <strong>{$bookTitle}</strong> has been approved.
                    <br><br>
                    <strong>Due Date:</strong> {$dueDateFormatted}
                </div>
                <p>Please collect the book from the library and return it by the due date.</p>
                <a href='#' class='button'>View My Loans</a>
            </div>
            <div class='footer'>
                <p>This is an automated message from LibraTrack Library Management System.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return ['subject' => $subject, 'html' => $html];
}

/**
 * Borrow request rejected template
 */
function tpl_new_borrow_request(string $librarianName, string $studentName, string $bookTitle, string $studentNo, int $durationDays): array {
    $subject = "New Borrow Request: {$bookTitle}";
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #2563eb; color: #fff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #fff; padding: 30px; border: 1px solid #e5e7eb; }
            .info-box { background: #eff6ff; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0; }
            .button { display: inline-block; padding: 12px 24px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; margin-top: 15px; }
            .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #f9fafb; border-radius: 0 0 8px 8px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>LibraTrack</h2>
            </div>
            <div class='content'>
                <h3>New Borrow Request</h3>
                <p>Dear {$librarianName},</p>
                <div class='info-box'>
                    <strong>Student:</strong> {$studentName} (ID: {$studentNo})<br>
                    <strong>Book:</strong> {$bookTitle}<br>
                    <strong>Duration:</strong> {$durationDays} days
                </div>
                <p>A new borrow request has been submitted and requires your review.</p>
                <a href='#' class='button'>Review Request</a>
            </div>
            <div class='footer'>
                <p>This is an automated message from LibraTrack Library Management System.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    return ['subject' => $subject, 'html' => $html];
}

function tpl_borrow_rejected(string $studentName, string $bookTitle, ?string $reason = null): array {
    $subject = "Borrow Request Update: {$bookTitle}";
    
    $reasonText = $reason ? "<p><strong>Reason:</strong> {$reason}</p>" : "";
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #6b7280; color: #fff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #fff; padding: 30px; border: 1px solid #e5e7eb; }
            .info-box { background: #f3f4f6; border-left: 4px solid #6b7280; padding: 15px; margin: 20px 0; }
            .button { display: inline-block; padding: 12px 24px; background: #6b7280; color: #fff; text-decoration: none; border-radius: 6px; margin-top: 15px; }
            .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #f9fafb; border-radius: 0 0 8px 8px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>LibraTrack</h2>
            </div>
            <div class='content'>
                <h3>Borrow Request Update</h3>
                <p>Dear {$studentName},</p>
                <div class='info-box'>
                    <p>Your request to borrow <strong>{$bookTitle}</strong> was not approved at this time.</p>
                    {$reasonText}
                </div>
                <p>You may submit a new request or contact the library for more information.</p>
                <a href='#' class='button'>Browse Books</a>
            </div>
            <div class='footer'>
                <p>This is an automated message from LibraTrack Library Management System.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return ['subject' => $subject, 'html' => $html];
}

/**
 * Critical overdue escalation template (for librarians)
 */
function tpl_overdue_critical(string $librarianName, string $bookTitle, string $studentName, int $daysOverdue): array {
    $subject = "Critical Overdue Escalation: {$bookTitle}";
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc2626; color: #fff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #fff; padding: 30px; border: 1px solid #e5e7eb; }
            .alert-box { background: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0; }
            .button { display: inline-block; padding: 12px 24px; background: #dc2626; color: #fff; text-decoration: none; border-radius: 6px; margin-top: 15px; }
            .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #f9fafb; border-radius: 0 0 8px 8px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>LibraTrack</h2>
            </div>
            <div class='content'>
                <h3>Critical Overdue Escalation</h3>
                <p>Dear {$librarianName},</p>
                <div class='alert-box'>
                    <strong>Action Required:</strong> A book has been overdue for more than 7 days and requires immediate attention.
                    <br><br>
                    <strong>Book:</strong> {$bookTitle}<br>
                    <strong>Student:</strong> {$studentName}<br>
                    <strong>Days Overdue:</strong> {$daysOverdue} days
                </div>
                <p>Please follow up with the student to ensure the book is returned promptly.</p>
                <a href='#' class='button'>View Overdue Loans</a>
            </div>
            <div class='footer'>
                <p>This is an automated message from LibraTrack Library Management System.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return ['subject' => $subject, 'html' => $html];
}

/**
 * Email failure notification template
 */
function tpl_email_failure(string $adminName, string $summary): array {
    $subject = "Email Delivery Failure Alert";
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc2626; color: #fff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #fff; padding: 30px; border: 1px solid #e5e7eb; }
            .alert-box { background: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0; }
            .button { display: inline-block; padding: 12px 24px; background: #dc2626; color: #fff; text-decoration: none; border-radius: 6px; margin-top: 15px; }
            .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #f9fafb; border-radius: 0 0 8px 8px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>LibraTrack</h2>
            </div>
            <div class='content'>
                <h3>Email Delivery Failure</h3>
                <p>Dear {$adminName},</p>
                <div class='alert-box'>
                    <strong>System Alert:</strong> The email notification system encountered failures while attempting to send emails.
                    <br><br>
                    <strong>Summary:</strong><br>
                    {$summary}
                </div>
                <p>Please check the SMTP settings and email queue status in the admin panel.</p>
                <a href='#' class='button'>View System Settings</a>
            </div>
            <div class='footer'>
                <p>This is an automated message from LibraTrack Library Management System.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return ['subject' => $subject, 'html' => $html];
}

/**
 * SMTP configuration failure template
 */
function tpl_smtp_config_failure(string $adminName, string $error): array {
    $subject = "SMTP Configuration Failure";
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc2626; color: #fff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #fff; padding: 30px; border: 1px solid #e5e7eb; }
            .alert-box { background: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0; }
            .button { display: inline-block; padding: 12px 24px; background: #dc2626; color: #fff; text-decoration: none; border-radius: 6px; margin-top: 15px; }
            .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; background: #f9fafb; border-radius: 0 0 8px 8px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>LibraTrack</h2>
            </div>
            <div class='content'>
                <h3>SMTP Configuration Failure</h3>
                <p>Dear {$adminName},</p>
                <div class='alert-box'>
                    <strong>System Error:</strong> SMTP configuration test failed.
                    <br><br>
                    <strong>Error:</strong><br>
                    {$error}
                </div>
                <p>Please review and update the SMTP settings in the admin panel.</p>
                <a href='#' class='button'>Configure SMTP</a>
            </div>
            <div class='footer'>
                <p>This is an automated message from LibraTrack Library Management System.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return ['subject' => $subject, 'html' => $html];
}




