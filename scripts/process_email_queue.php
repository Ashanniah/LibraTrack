<?php
/**
 * Email Queue Processor
 * Processes queued email notifications
 * 
 * Usage: php scripts/process_email_queue.php [limit]
 * 
 * Can be run via cron:
 * */5 * * * * cd /path/to/libratrack && php scripts/process_email_queue.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get limit from command line or use default
$limit = isset($argv[1]) ? max(1, (int)$argv[1]) : 25;

try {
    require_once __DIR__ . '/../includes/email_notifications.php';
    
    // Get PDO connection
    $pdo = DB::connection()->getPdo();
    
    echo "Processing email queue (limit: {$limit})...\n";
    
    $stats = process_email_queue($pdo, $limit);
    
    echo "Queue processing completed.\n";
    echo "  Processed: {$stats['processed']}\n";
    echo "  Sent: {$stats['sent']}\n";
    echo "  Failed: {$stats['failed']}\n";
    
    // Log to system_logs
    try {
        DB::table('system_logs')->insert([
            'actor_user_id' => null,
            'actor_name' => 'System',
            'actor_role' => 'admin',
            'action' => 'EMAIL_QUEUE_PROCESSED',
            'entity_type' => 'system',
            'entity_id' => null,
            'details' => "Processed {$stats['processed']} emails. Sent: {$stats['sent']}, Failed: {$stats['failed']}",
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Cron Script',
            'created_at' => now(),
        ]);
    } catch (Exception $e) {
        // Ignore logging errors
    }
    
    exit(0);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}





