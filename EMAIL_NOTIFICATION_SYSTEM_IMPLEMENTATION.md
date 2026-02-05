# Email Notification System Implementation

## Overview
This document describes the complete email-only notification system implementation for LibraTrack, where **EMAIL is the single source of truth** for all notifications.

## Global Rules
- ✅ **Email-only channel** - No push notifications, no real-time in-app alerts
- ✅ **SMTP-based delivery** - Uses configured SMTP settings from database
- ✅ **Backend-triggered** - Emails sent automatically by system events, not UI buttons
- ✅ **Persistent logging** - All email attempts logged to `email_logs` table
- ✅ **Read-only history** - UI shows email history logs, never triggers emails

## Database Structure

### email_logs Table
Created via migration: `2025_12_20_100000_create_email_logs_table.php`

**Fields:**
- `id` - Primary key
- `user_id` - Recipient user ID
- `role` - Recipient role (student, librarian, admin)
- `recipient_email` - Email address
- `event_type` - Event type (BORROW_REQUEST_APPROVED, LOW_STOCK, etc.)
- `subject` - Email subject
- `body_preview` - First 500 chars of email body
- `status` - 'sent' or 'failed'
- `error_message` - Error details if failed
- `related_entity_type` - Related entity (loan, book, borrow_request, etc.)
- `related_entity_id` - Related entity ID
- `created_at` - When email was queued
- `sent_at` - When email was actually sent

**Indexes:** user_id, role, event_type, status, created_at, (user_id, created_at)

## Role-Based Email Events

### STUDENT Receives Emails For:
1. ✅ **BORROW_REQUEST_APPROVED** - When librarian approves borrow request
2. ✅ **BORROW_REQUEST_REJECTED** - When librarian rejects borrow request
3. ✅ **DUE_SOON** - X days before due date (scheduled job)
4. ✅ **OVERDUE** - Past due date (scheduled job)
5. ⚠️ **ACCOUNT_STATUS_CHANGE** - When account is deactivated/reactivated (TODO: implement in UserController)
6. ⚠️ **PASSWORD_RESET** - When student requests password reset (TODO: implement in AuthController)

**Student does NOT receive:** System errors, SMTP status, inventory alerts, admin config changes

### LIBRARIAN Receives Emails For:
1. ✅ **NEW_BORROW_REQUEST** - When student submits borrow request
2. ✅ **LOW_STOCK** - When book quantity < threshold
3. ⚠️ **OVERDUE_SUMMARY** - Scheduled summary of overdue books (TODO: implement in scheduled job)
4. ⚠️ **ACCOUNT_STATUS_CHANGE** - When admin updates librarian status (TODO: implement in UserController)
5. ⚠️ **PASSWORD_RESET** - When librarian requests password reset (TODO: implement in AuthController)

**Librarian does NOT receive:** SMTP configuration status, system-level failures, admin-only alerts

### ADMIN Receives Emails For:
1. ✅ **EMAIL_FAILURE** - When email sending fails repeatedly
2. ✅ **SMTP_CONFIG_FAILURE** - When SMTP test fails
3. ⚠️ **SMTP_MISCONFIGURATION** - When SMTP settings are invalid (TODO: implement)
4. ⚠️ **CRITICAL_SYSTEM_ERROR** - System errors (TODO: implement in error handlers)
5. ⚠️ **ACCOUNT_ROLE_CHANGE** - When admin changes user roles (TODO: implement in UserController)
6. ⚠️ **SECURITY_EVENT** - Repeated login failures (TODO: implement in AuthController)
7. ⚠️ **BACKUP_ALERT** - Backup completion/failure (TODO: implement in backup scripts)
8. ⚠️ **MAINTENANCE_ALERT** - Maintenance scheduled/completed (TODO: implement)

**Admin does NOT receive:** Borrow approvals, due reminders, student transaction emails

## Email Sending Flow

1. **System event occurs** (e.g., borrow request approved)
2. **Backend detects event** (in controller/service)
3. **Email queued** via `queue_email()` function
4. **Email template built** via `build_email_template()` function
5. **SMTP sends email** via `send_mail()` function using configured SMTP settings
6. **Email logged** to `email_logs` table with status 'sent' or 'failed'
7. **Email delivered** to real inbox (Gmail/Outlook/etc.)

**No UI click required** - All emails are triggered automatically by backend events.

## API Endpoints

### GET /api/email-notifications
**Purpose:** Get email notification history for current user

**Query Parameters:**
- `page` - Page number (default: 1)
- `limit` - Items per page (default: 20, max: 100)
- `event_type` - Filter by event type
- `status` - Filter by status (all, sent, failed)
- `date_from` - Filter from date (YYYY-MM-DD)
- `date_to` - Filter to date (YYYY-MM-DD)
- `search` - Search by subject

**Response:**
```json
{
  "success": true,
  "logs": [
    {
      "id": 1,
      "event_type": "BORROW_REQUEST_APPROVED",
      "subject": "Borrow Request Approved: Book Title",
      "body_preview": "Your borrow request...",
      "status": "sent",
      "error_message": null,
      "recipient_email": "u***@example.com",
      "related_entity_type": "loan",
      "related_entity_id": 123,
      "created_at": "2025-12-20 10:30:00",
      "sent_at": "2025-12-20 10:30:05"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 50,
    "pages": 3
  }
}
```

### GET /api/email-notifications/all
**Purpose:** Get all email logs (admin only)

**Same query parameters as above, plus:**
- `role` - Filter by recipient role

**Response:** Same format, but includes full email addresses (not masked)

### GET /api/email-notifications/event-types
**Purpose:** Get available event types for current user

**Response:**
```json
{
  "success": true,
  "event_types": ["BORROW_REQUEST_APPROVED", "DUE_SOON", "OVERDUE"]
}
```

## Frontend Pages

### Email Notification History Page
**File:** `email-notification-history.html`

**Features:**
- ✅ Unified page for all roles (Student, Librarian, Admin)
- ✅ Table view with columns: Date/Time, Event Type, Subject, Recipient, Status, Actions
- ✅ Filters: Event Type, Status, Date Range, Search
- ✅ Pagination
- ✅ View Details modal
- ✅ Empty state
- ✅ Loading skeleton
- ✅ Responsive design

**Access:**
- Student: Sidebar → Email History
- Librarian: Sidebar → Email History
- Admin: Sidebar → Email History

## Implementation Files

### Backend
- `database/migrations/2025_12_20_100000_create_email_logs_table.php` - Database migration
- `create-email-logs-table.sql` - SQL script for manual creation
- `app/Http/Controllers/EmailLogController.php` - API controller
- `includes/mailer.php` - Email sending with logging
- `includes/email_notifications.php` - Email queue and processing
- `includes/email_templates.php` - Email templates

### Frontend
- `email-notification-history.html` - Unified email history page
- `assets/js/sidebar.js` - Updated sidebar navigation

### Updated Controllers
- `app/Http/Controllers/BorrowRequestController.php` - Added NEW_BORROW_REQUEST email
- `routes/api.php` - Added email notification routes

## Email Templates

All templates are in `includes/email_templates.php`:
- `tpl_overdue()` - Overdue book notification
- `tpl_due_soon()` - Due soon reminder
- `tpl_borrow_approved()` - Borrow request approved
- `tpl_borrow_rejected()` - Borrow request rejected
- `tpl_new_borrow_request()` - New borrow request (for librarians)
- `tpl_low_stock()` - Low stock alert
- `tpl_overdue_critical()` - Critical overdue escalation
- `tpl_email_failure()` - Email failure notification
- `tpl_smtp_config_failure()` - SMTP config failure

## Setup Instructions

### 1. Create Database Table
Run the migration or SQL script:
```bash
php artisan migrate
```
OR
```sql
-- Run create-email-logs-table.sql in your database
```

### 2. Configure SMTP Settings
Go to Admin → System Settings → SMTP Configuration
- Enter SMTP host (e.g., smtp.gmail.com)
- Enter SMTP port (e.g., 587)
- Enter SMTP username (your email)
- Enter SMTP password (app password for Gmail)
- Test SMTP connection

### 3. Process Email Queue
Set up a cron job to process the email queue:
```bash
# Run every 5 minutes
*/5 * * * * php /path/to/scripts/process_email_queue.php
```

### 4. Scheduled Jobs
Set up cron jobs for scheduled notifications:
```bash
# Due soon reminders (daily at 9 AM)
0 9 * * * php /path/to/scripts/run_due_soon_scan.php

# Overdue notifications (daily at 9 AM)
0 9 * * * php /path/to/scripts/run_overdue_scan.php
```

## Testing Plan

### Manual Test Steps

1. **Test Student Email - Borrow Request Approved**
   - Student submits borrow request
   - Librarian approves request
   - ✅ Check student's email inbox for approval email
   - ✅ Check Email Notification History page shows the email log

2. **Test Student Email - Borrow Request Rejected**
   - Student submits borrow request
   - Librarian rejects request
   - ✅ Check student's email inbox for rejection email
   - ✅ Check Email Notification History page

3. **Test Librarian Email - New Borrow Request**
   - Student submits borrow request
   - ✅ Check librarian's email inbox for new request notification
   - ✅ Check Email Notification History page

4. **Test Librarian Email - Low Stock**
   - Add book with quantity < threshold
   - ✅ Check librarian's email inbox for low stock alert
   - ✅ Check Email Notification History page

5. **Test Admin Email - SMTP Config Failure**
   - Configure invalid SMTP settings
   - Test SMTP connection
   - ✅ Check admin's email inbox for failure notification
   - ✅ Check Email Notification History page

6. **Test Scheduled Emails**
   - Create loan with due date tomorrow
   - Run due soon scan script
   - ✅ Check student's email inbox for due soon reminder
   - ✅ Check Email Notification History page

7. **Test Email History Page**
   - Login as Student/Librarian/Admin
   - Navigate to Email History
   - ✅ Verify filters work
   - ✅ Verify pagination works
   - ✅ Verify view details modal works
   - ✅ Verify empty state displays correctly

## Notes

- All emails are sent via SMTP using PHPMailer
- Email logs are created immediately when email is sent (success or failure)
- Failed emails are logged with error messages
- Email addresses are masked for non-admin users (privacy)
- Admin can view all email logs with full email addresses
- Email queue is processed asynchronously via cron job
- In-app notifications are created ONLY after successful email delivery (read-only logs)

## TODO Items

The following events need implementation:
- [ ] ACCOUNT_STATUS_CHANGE emails (UserController update method)
- [ ] PASSWORD_RESET emails (AuthController)
- [ ] OVERDUE_SUMMARY emails (scheduled job)
- [ ] CRITICAL_SYSTEM_ERROR emails (error handlers)
- [ ] ACCOUNT_ROLE_CHANGE emails (UserController)
- [ ] SECURITY_EVENT emails (AuthController login failure tracking)
- [ ] BACKUP_ALERT emails (backup scripts)
- [ ] MAINTENANCE_ALERT emails (maintenance scripts)


