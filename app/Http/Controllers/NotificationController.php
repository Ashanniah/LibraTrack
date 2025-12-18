<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\AuditLog;

class NotificationController extends BaseController
{
    /**
     * Get unread notification count
     * Only counts notifications that correspond to successfully sent emails
     */
    public function unreadCount(Request $request)
    {
        $user = $this->requireLogin();
        $userRole = strtolower($user['role'] ?? '');
        
        // Define allowed notification types per role
        $allowedTypes = [];
        if ($userRole === 'student') {
            $allowedTypes = [
                'BORROW_REQUEST_APPROVED',
                'BORROW_REQUEST_REJECTED',
                'DUE_SOON',
                'OVERDUE'
            ];
        } elseif ($userRole === 'librarian') {
            $allowedTypes = [
                'NEW_BORROW_REQUEST',
                'LOW_STOCK',
                'OVERDUE_SUMMARY'
            ];
        }
        // Admin has no user-facing notifications
        
        $query = DB::table('notifications')
            ->where('recipient_user_id', $user['id'])
            ->where('recipient_role', $userRole)
            ->where('is_read', false);
        
        if (!empty($allowedTypes)) {
            $query->whereIn('type', $allowedTypes);
        } else {
            // Admin or invalid role - return 0
            return response()->json(['unread' => 0]);
        }
        
        $count = $query->count();
        
        return response()->json(['unread' => $count]);
    }
    
    /**
     * List notifications with filters and pagination
     * Only shows notifications that correspond to successfully sent emails
     * Role-based filtering applied
     */
    public function list(Request $request)
    {
        $user = $this->requireLogin();
        $userRole = strtolower($user['role'] ?? '');
        
        $status = $request->input('status', 'all'); // all, unread
        $type = $request->input('type', '');
        $page = max(1, (int)$request->input('page', 1));
        $limit = max(1, min(100, (int)$request->input('limit', 20)));
        
        // Define allowed notification types per role
        $allowedTypes = [];
        if ($userRole === 'student') {
            $allowedTypes = [
                'BORROW_REQUEST_APPROVED',
                'BORROW_REQUEST_REJECTED',
                'DUE_SOON',
                'OVERDUE'
            ];
        } elseif ($userRole === 'librarian') {
            $allowedTypes = [
                'NEW_BORROW_REQUEST',
                'LOW_STOCK',
                'OVERDUE_SUMMARY'
            ];
        } elseif ($userRole === 'admin') {
            // Admin has no user-facing notifications (relies on system logs)
            $allowedTypes = [];
        }
        
        $query = DB::table('notifications')
            ->where('recipient_user_id', $user['id'])
            ->where('recipient_role', $userRole);
        
        // Filter by allowed types for role
        if (!empty($allowedTypes)) {
            $query->whereIn('type', $allowedTypes);
        } else {
            // Admin or invalid role - return empty
            $query->whereRaw('1 = 0'); // Force empty result
        }
        
        // Filter by status
        if ($status === 'unread') {
            $query->where('is_read', false);
        }
        
        // Filter by type
        if (!empty($type)) {
            $query->where('type', $type);
        }
        
        // Get total count
        $total = $query->count();
        
        // Get paginated results
        // Sort: Unread first, then by date (newest first)
        $offset = ($page - 1) * $limit;
        $items = $query->select([
                'id',
                'type',
                'title',
                'message',
                'entity_type',
                'entity_id',
                'deep_link',
                'is_read',
                'created_at'
            ])
            ->orderBy('is_read', 'asc') // Unread first (false comes before true)
            ->orderBy('created_at', 'desc') // Then newest first
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'type' => $item->type,
                    'title' => $item->title,
                    'message' => $item->message,
                    'entity_type' => $item->entity_type,
                    'entity_id' => $item->entity_id,
                    'deep_link' => $item->deep_link,
                    'is_read' => (bool)$item->is_read,
                    'created_at' => $item->created_at,
                ];
            });
        
        return response()->json([
            'items' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => max(1, (int)ceil($total / $limit))
        ]);
    }
    
    /**
     * Mark notification as read
     */
    public function markRead(Request $request)
    {
        $user = $this->requireLogin();
        
        $notificationId = (int)$request->input('notification_id');
        
        if ($notificationId < 1) {
            return response()->json(['ok' => false, 'error' => 'Invalid notification ID'], 400);
        }
        
        // Verify notification belongs to user
        $notification = DB::table('notifications')
            ->where('id', $notificationId)
            ->where('recipient_user_id', $user['id'])
            ->first();
        
        if (!$notification) {
            return response()->json(['ok' => false, 'error' => 'Notification not found'], 404);
        }
        
        // Mark as read
        DB::table('notifications')
            ->where('id', $notificationId)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);
        
        // Log action
        try {
            AuditLog::logAction(
                $user,
                'NOTIF_READ',
                'notification',
                $notificationId,
                "Marked notification as read"
            );
        } catch (\Exception $e) {
            // Ignore logging errors
        }
        
        return response()->json(['ok' => true]);
    }
    
    /**
     * Mark all notifications as read for current user
     */
    public function markAllRead(Request $request)
    {
        $user = $this->requireLogin();
        
        $count = DB::table('notifications')
            ->where('recipient_user_id', $user['id'])
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);
        
        // Log action
        try {
            AuditLog::logAction(
                $user,
                'NOTIF_READ_ALL',
                'notification',
                null,
                "Marked {$count} notifications as read"
            );
        } catch (\Exception $e) {
            // Ignore logging errors
        }
        
        return response()->json(['ok' => true, 'count' => $count]);
    }
    
    /**
     * Process email queue (admin only)
     */
    public function processEmailQueue(Request $request)
    {
        $user = $this->requireRole(['admin']);
        
        $limit = max(1, min(100, (int)$request->input('limit', 25)));
        
        try {
            require_once __DIR__ . '/../../includes/email_notifications.php';
            $pdo = DB::connection()->getPdo();
            
            $stats = process_email_queue($pdo, $limit);
            
            return response()->json([
                'ok' => true,
                'processed' => $stats['processed'],
                'sent' => $stats['sent'],
                'failed' => $stats['failed']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Failed to process queue: ' . $e->getMessage()
            ], 500);
        }
    }
}

