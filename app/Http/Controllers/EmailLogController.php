<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailLogController extends BaseController
{
    /**
     * Get email notification history for current user
     * GET /api/email-notifications
     */
    public function index(Request $request)
    {
        $user = $this->requireLogin();
        $userRole = strtolower($user['role'] ?? '');
        $userId = (int)$user['id'];
        
        // Pagination
        $page = max(1, (int)$request->input('page', 1));
        $limit = max(1, min(100, (int)$request->input('limit', 20)));
        $offset = ($page - 1) * $limit;
        
        // Filters
        $eventType = $request->input('event_type', '');
        $status = $request->input('status', 'all'); // all, sent, failed
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');
        $search = $request->input('search', '');
        
        // Build query
        $query = DB::table('email_logs')
            ->where('user_id', $userId);
        
        // Filter by event type
        if (!empty($eventType)) {
            $query->where('event_type', $eventType);
        }
        
        // Filter by status
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        
        // Filter by date range
        if (!empty($dateFrom)) {
            $query->where('created_at', '>=', $dateFrom . ' 00:00:00');
        }
        if (!empty($dateTo)) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }
        
        // Search by subject
        if (!empty($search)) {
            $query->where('subject', 'LIKE', '%' . $search . '%');
        }
        
        // Get total count
        $total = $query->count();
        
        // Get paginated results
        $logs = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(function($log) {
                return [
                    'id' => $log->id,
                    'event_type' => $log->event_type,
                    'subject' => $log->subject,
                    'body_preview' => $log->body_preview,
                    'status' => $log->status,
                    'error_message' => $log->error_message,
                    'recipient_email' => $this->maskEmail($log->recipient_email),
                    'related_entity_type' => $log->related_entity_type,
                    'related_entity_id' => $log->related_entity_id,
                    'created_at' => $log->created_at,
                    'sent_at' => $log->sent_at,
                ];
            });
        
        return response()->json([
            'success' => true,
            'logs' => $logs,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    /**
     * Get all email logs (admin only)
     * GET /api/email-notifications/all
     */
    public function all(Request $request)
    {
        $user = $this->requireRole(['admin']);
        
        // Pagination
        $page = max(1, (int)$request->input('page', 1));
        $limit = max(1, min(100, (int)$request->input('limit', 50)));
        $offset = ($page - 1) * $limit;
        
        // Filters
        $eventType = $request->input('event_type', '');
        $status = $request->input('status', 'all');
        $role = $request->input('role', '');
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');
        $search = $request->input('search', '');
        
        // Build query
        $query = DB::table('email_logs');
        
        // Apply filters
        if (!empty($eventType)) {
            $query->where('event_type', $eventType);
        }
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if (!empty($role)) {
            $query->where('role', $role);
        }
        if (!empty($dateFrom)) {
            $query->where('created_at', '>=', $dateFrom . ' 00:00:00');
        }
        if (!empty($dateTo)) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('subject', 'LIKE', '%' . $search . '%')
                  ->orWhere('recipient_email', 'LIKE', '%' . $search . '%');
            });
        }
        
        // Get total count
        $total = $query->count();
        
        // Get paginated results
        $logs = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(function($log) {
                return [
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'role' => $log->role,
                    'event_type' => $log->event_type,
                    'subject' => $log->subject,
                    'body_preview' => $log->body_preview,
                    'status' => $log->status,
                    'error_message' => $log->error_message,
                    'recipient_email' => $log->recipient_email, // Full email for admin
                    'related_entity_type' => $log->related_entity_type,
                    'related_entity_id' => $log->related_entity_id,
                    'created_at' => $log->created_at,
                    'sent_at' => $log->sent_at,
                ];
            });
        
        return response()->json([
            'success' => true,
            'logs' => $logs,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
    
    /**
     * Get available event types for filtering
     * GET /api/email-notifications/event-types
     */
    public function eventTypes(Request $request)
    {
        $user = $this->requireLogin();
        $userRole = strtolower($user['role'] ?? '');
        $userId = (int)$user['id'];
        
        // Get distinct event types for this user
        $eventTypes = DB::table('email_logs')
            ->where('user_id', $userId)
            ->distinct()
            ->pluck('event_type')
            ->filter()
            ->values()
            ->toArray();
        
        return response()->json([
            'success' => true,
            'event_types' => $eventTypes
        ]);
    }
    
    /**
     * Mask email address for privacy
     */
    private function maskEmail(string $email): string
    {
        if (empty($email)) {
            return '';
        }
        
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        
        $username = $parts[0];
        $domain = $parts[1];
        
        // Mask username: show first 2 chars and last char
        if (strlen($username) <= 3) {
            $maskedUsername = str_repeat('*', strlen($username));
        } else {
            $maskedUsername = substr($username, 0, 2) . str_repeat('*', strlen($username) - 3) . substr($username, -1);
        }
        
        return $maskedUsername . '@' . $domain;
    }
}


