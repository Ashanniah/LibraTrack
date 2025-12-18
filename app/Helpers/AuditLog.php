<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class AuditLog
{
    /**
     * Log an action to the system_logs table
     * Fails safely - if logging fails, it won't break the main action
     * 
     * @param array $actor User array with 'id', 'first_name', 'last_name', 'role'
     * @param string $action Action code (e.g., 'BOOK_CREATE', 'USER_UPDATE')
     * @param string|null $entityType Entity type (e.g., 'book', 'user', 'category')
     * @param int|null $entityId Entity ID
     * @param string|null $details Human-readable details
     * @return bool Success status
     */
    public static function logAction($actor, $action, $entityType = null, $entityId = null, $details = null)
    {
        try {
            // Build actor name from first_name and last_name
            $actorName = trim(($actor['first_name'] ?? '') . ' ' . ($actor['last_name'] ?? ''));
            if (empty($actorName)) {
                $actorName = $actor['email'] ?? 'Unknown';
            }
            
            // Get actor role, default to 'student' if not set
            $actorRole = strtolower($actor['role'] ?? 'student');
            if (!in_array($actorRole, ['admin', 'librarian', 'student'], true)) {
                $actorRole = 'student';
            }
            
            // Get IP address
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            // Handle proxy headers
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ipAddress = trim($ips[0]);
            }
            
            // Get user agent
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            if ($userAgent && strlen($userAgent) > 255) {
                $userAgent = substr($userAgent, 0, 255);
            }
            
            // Insert log entry
            DB::table('system_logs')->insert([
                'actor_user_id' => !empty($actor['id']) ? (int)$actor['id'] : null,
                'actor_name' => $actorName,
                'actor_role' => $actorRole,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId ? (int)$entityId : null,
                'details' => $details,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'created_at' => now(),
            ]);
            
            return true;
        } catch (\Exception $e) {
            // Fail silently - log error but don't break main action
            error_log('AuditLog::logAction failed: ' . $e->getMessage());
            return false;
        }
    }
}






