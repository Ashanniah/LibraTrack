<?php
/**
 * Due Soon Detection Script
 * Scans for loans due in 1 day and sends notifications
 * 
 * Usage: php scripts/run_due_soon_scan.php
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
    echo "Starting due soon scan...\n";
    
    // Find loans due tomorrow
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $dueSoonLoans = DB::table('loans as l')
        ->join('books as b', 'b.id', '=', 'l.book_id')
        ->join('users as u', 'u.id', '=', 'l.student_id')
        ->where('l.status', 'borrowed')
        ->whereRaw('DATE(l.due_at) = ?', [$tomorrow])
        ->whereNull('l.returned_at')
        ->select('l.*', 'b.title as book_title', 'u.first_name', 'u.last_name')
        ->get();
    
    $pdo = DB::connection()->getPdo();
    $count = 0;
    $emailQueued = 0;
    
    foreach ($dueSoonLoans as $loan) {
        try {
            // Queue email (OPTIONAL for DUE_SOON)
            // In-app notification created automatically when email is sent successfully
            $deliveryId = NotificationHelper::queueEmail($loan->student_id, 'DUE_SOON', 'loan', $loan->id);
            if ($deliveryId > 0) {
                $emailQueued++;
                $count++;
                echo "Queued email for student {$loan->first_name} {$loan->last_name} about book due soon: {$loan->book_title}\n";
            } else {
                echo "Skipped (policy or spam control): Loan ID {$loan->id}\n";
            }
        } catch (\Exception $e) {
            echo "Error processing loan ID {$loan->id}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "Due soon scan completed. Notified {$count} student(s). Queued {$emailQueued} email(s).\n";
    
    // Process email queue
    echo "Processing email queue...\n";
    $stats = process_email_queue($pdo, 50);
    echo "Email queue processed. Sent: {$stats['sent']}, Failed: {$stats['failed']}\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

