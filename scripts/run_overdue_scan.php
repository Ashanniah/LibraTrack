<?php
/**
 * Overdue Detection Script
 * Scans for overdue loans and sends notifications
 * Run this daily via cron or manually
 * 
 * Usage: php scripts/run_overdue_scan.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Helpers\NotificationHelper;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Load email queue functions
require_once __DIR__ . '/../includes/email_notifications.php';

try {
    echo "Starting overdue scan...\n";
    
    // Find all overdue loans (due_date < today and not returned)
    $today = date('Y-m-d');
    $overdueLoans = DB::table('loans as l')
        ->join('books as b', 'b.id', '=', 'l.book_id')
        ->join('users as u', 'u.id', '=', 'l.student_id')
        ->where('l.status', 'borrowed')
        ->whereRaw('DATE(l.due_at) < ?', [$today])
        ->whereNull('l.returned_at')
        ->select('l.*', 'b.title as book_title', 'u.first_name', 'u.last_name', 'u.school_id')
        ->get();
    
    $pdo = DB::connection()->getPdo();
    $count = 0;
    $emailQueued = 0;
    
    $librarianEscalations = 0;
    
    foreach ($overdueLoans as $loan) {
        try {
            // Queue email to student (REQUIRED for OVERDUE)
            // In-app notification created automatically when email is sent successfully
            $deliveryId = NotificationHelper::queueEmail($loan->student_id, 'OVERDUE', 'loan', $loan->id);
            if ($deliveryId > 0) {
                $emailQueued++;
                $count++;
                echo "Queued email for student {$loan->first_name} {$loan->last_name} about overdue book: {$loan->book_title}\n";
            } else {
                echo "Skipped (spam control or policy): Loan ID {$loan->id}\n";
            }
            
            // Critical escalation: If overdue > 7 days, notify librarians
            $daysOverdue = (int)((strtotime($today) - strtotime($loan->due_at)) / 86400);
            if ($daysOverdue > 7) {
                // Find librarians for the student's school
                $librariansQuery = DB::table('users')
                    ->where('role', 'librarian')
                    ->where('status', 'active');
                
                if ($loan->school_id > 0) {
                    $librariansQuery->where('school_id', $loan->school_id);
                }
                
                $librarians = $librariansQuery->get();
                
                foreach ($librarians as $librarian) {
                    try {
                        // Queue critical overdue escalation email
                        $escDeliveryId = NotificationHelper::queueEmail(
                            $librarian->id,
                            'OVERDUE_CRITICAL',
                            'loan',
                            $loan->id
                        );
                        if ($escDeliveryId > 0) {
                            $librarianEscalations++;
                            echo "Queued critical overdue escalation for librarian {$librarian->first_name} {$librarian->last_name} about loan ID {$loan->id}\n";
                        }
                    } catch (\Exception $e) {
                        echo "Error queueing escalation for librarian {$librarian->id}: " . $e->getMessage() . "\n";
                    }
                }
            }
        } catch (\Exception $e) {
            echo "Error processing loan ID {$loan->id}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "Overdue scan completed. Notified {$count} student(s). Queued {$emailQueued} email(s). Escalated {$librarianEscalations} critical overdue(s) to librarians.\n";
    
    echo "Overdue scan completed. Notified {$count} student(s). Queued {$emailQueued} email(s).\n";
    
    // Process email queue
    echo "Processing email queue...\n";
    $stats = process_email_queue($pdo, 50);
    echo "Email queue processed. Sent: {$stats['sent']}, Failed: {$stats['failed']}\n";
    
    // Log to system_logs
    try {
        DB::table('system_logs')->insert([
            'actor_user_id' => null,
            'actor_name' => 'System',
            'actor_role' => 'admin',
            'action' => 'OVERDUE_SCAN',
            'entity_type' => 'system',
            'entity_id' => null,
            'details' => "Overdue scan completed. Found {$count} overdue loan(s).",
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Cron Script',
            'created_at' => now(),
        ]);
    } catch (\Exception $e) {
        // Ignore logging errors
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

