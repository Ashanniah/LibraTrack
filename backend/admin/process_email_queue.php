<?php
/**
 * Admin Endpoint: Process Email Queue
 * Allows admins to manually trigger email queue processing
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../includes/email_notifications.php';

header('Content-Type: application/json; charset=utf-8');

// Only admins can trigger queue processing
$user = require_role($conn, ['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$limit = isset($_POST['limit']) ? max(1, min(100, (int)$_POST['limit'])) : 25;

try {
    // Get PDO connection from db.php
    global $pdo;
    if (!isset($pdo)) {
        // Fallback: create PDO connection
        require_once __DIR__ . '/../config.php';
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    
    $stats = process_email_queue($pdo, $limit);
    
    json_response([
        'ok' => true,
        'processed' => $stats['processed'],
        'sent' => $stats['sent'],
        'failed' => $stats['failed']
    ]);
} catch (Exception $e) {
    json_response([
        'ok' => false,
        'error' => 'Failed to process queue: ' . $e->getMessage()
    ], 500);
}

