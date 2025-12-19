# How to Test Email Notifications

## Step-by-Step Guide to Test Email Sending

### 1. Configure SMTP Settings (Gmail Example)

#### For Gmail:
1. **Go to Admin Dashboard → System Settings**
2. **Fill in SMTP Configuration:**
   - **SMTP Host:** `smtp.gmail.com`
   - **SMTP Port:** `587`
   - **SMTP Username:** Your Gmail address (e.g., `yourname@gmail.com`)
   - **SMTP Password:** Gmail App Password (see below)
   - **From Email:** Your Gmail address (same as username)
   - **From Name:** `LibraTrack` (or your preferred name)
   - **Encryption:** `TLS`

#### How to Get Gmail App Password:
1. Go to your Google Account: https://myaccount.google.com/
2. Click **Security** → **2-Step Verification** (enable if not enabled)
3. Scroll down to **App passwords**
4. Select **Mail** and **Other (Custom name)**
5. Enter "LibraTrack" as the name
6. Click **Generate**
7. Copy the 16-character password (no spaces)
8. Use this password in the SMTP Password field

### 2. Test SMTP Connection (Sends Actual Test Email)

1. **In Admin Settings page**, fill in all SMTP fields
2. **Click "Test Connection" button**
3. **The system will:**
   - Connect to the SMTP server
   - Send a test email to your SMTP Username email address
4. **Check the result:**
   - ✅ **Success:** "SMTP connection successful! A test email has been sent to [your-email]. Please check your inbox (and spam folder)."
   - ❌ **Error:** Check the error message and verify your settings
5. **Check your email inbox:**
   - Look for email with subject: "LibraTrack SMTP Test - Connection Successful"
   - If not in inbox, check **Spam/Junk folder**
   - The email will contain test details confirming SMTP is working

### 3. Save SMTP Settings

1. After successful test, click **"Save SMTP Settings"**
2. The dashboard will now show **"Configured"** (green) instead of "Not Configured"

### 4. Test Actual Email Notifications

#### Test Student Email - Borrow Request Approved:
1. **Login as Student**
2. **Submit a borrow request** for any book
3. **Login as Librarian**
4. **Approve the borrow request**
5. ✅ **Check student's email inbox** - Should receive "Borrow Request Approved" email
6. ✅ **Check Email Notification History page** - Should show the email log

#### Test Librarian Email - New Borrow Request:
1. **Login as Student**
2. **Submit a borrow request** for any book
3. ✅ **Check librarian's email inbox** - Should receive "New Borrow Request" email
4. ✅ **Check Email Notification History page** - Should show the email log

#### Test Librarian Email - Low Stock:
1. **Login as Librarian/Admin**
2. **Add a book** with quantity less than threshold (default: 3)
3. ✅ **Check librarian's email inbox** - Should receive "Low Stock Alert" email
4. ✅ **Check Email Notification History page** - Should show the email log

#### Test Scheduled Emails (Due Soon / Overdue):
1. **Create a loan** with due date tomorrow (for due soon) or past date (for overdue)
2. **Run the scheduled script:**
   ```bash
   php scripts/run_due_soon_scan.php    # For due soon reminders
   php scripts/run_overdue_scan.php      # For overdue notifications
   ```
3. ✅ **Check student's email inbox** - Should receive the reminder/overdue email
4. ✅ **Check Email Notification History page** - Should show the email log

### 5. Check Email Logs

1. **Login as any user** (Student/Librarian/Admin)
2. **Go to Email Notification History** (in sidebar)
3. **Verify:**
   - Email appears in the list
   - Status shows "Sent" (green badge)
   - Date/Time is correct
   - Subject and preview are correct
4. **Click "View Details"** to see full email information

### 6. Verify Email Delivery

1. **Check your email inbox** (Gmail/Outlook/etc.)
2. **Check Spam/Junk folder** if email not in inbox
3. **Verify email content:**
   - Subject line is correct
   - HTML formatting is correct
   - All information is accurate
   - Links work (if any)

### 7. Troubleshooting

#### If emails are not received:
1. **Check SMTP Status** on dashboard - Should show "Configured"
2. **Check Email Notification History** - Look for "Failed" status
3. **Check error message** in email log details
4. **Verify SMTP settings:**
   - Gmail: Use App Password, not regular password
   - Port: 587 for TLS, 465 for SSL
   - Host: Correct SMTP server (smtp.gmail.com, smtp.outlook.com, etc.)
5. **Check server logs** for detailed error messages
6. **Test SMTP connection** again using "Test Connection" button

#### Common Gmail Issues:
- **"Username and Password not accepted"** → Use App Password, not regular password
- **"Connection timeout"** → Check firewall/port 587 is open
- **"Authentication failed"** → Enable 2-Step Verification first, then create App Password

#### Common Outlook Issues:
- **SMTP Host:** `smtp-mail.outlook.com` or `smtp.office365.com`
- **Port:** `587` (TLS) or `465` (SSL)
- **Username:** Full email address
- **Password:** Your Microsoft account password (or App Password if 2FA enabled)

### 8. Process Email Queue

If emails are queued but not sent:
1. **Run the email queue processor:**
   ```bash
   php scripts/process_email_queue.php
   ```
2. **Or set up a cron job** to run every 5 minutes:
   ```bash
   */5 * * * * php /path/to/scripts/process_email_queue.php
   ```

### 9. Test Email Function (Admin Only)

A "Send Test Email" feature can be added to send a test email to any address to verify the system is working.

