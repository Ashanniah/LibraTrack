<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogController extends BaseController
{
    public function list(Request $request)
    {
        // Only admin can access system logs
        $user = $this->requireRole(['admin']);

        // Get query parameters
        $q = trim($request->input('q', ''));
        $action = trim($request->input('action', ''));
        $from = trim($request->input('from', ''));
        $to = trim($request->input('to', ''));
        $page = max(1, (int)$request->input('page', 1));
        $limit = max(1, min(100, (int)$request->input('limit', 20)));

        // Build query
        $query = DB::table('system_logs');

        // Search across actor_name, action, and details
        if ($q !== '') {
            $query->where(function($qry) use ($q) {
                $qry->where('actor_name', 'LIKE', "%{$q}%")
                    ->orWhere('action', 'LIKE', "%{$q}%")
                    ->orWhere('details', 'LIKE', "%{$q}%");
            });
        }

        // Filter by action
        if ($action !== '' && $action !== 'all') {
            $query->where('action', $action);
        }

        // Filter by date range
        if ($from !== '') {
            try {
                $fromDate = date('Y-m-d 00:00:00', strtotime($from));
                $query->where('created_at', '>=', $fromDate);
            } catch (\Exception $e) {
                // Invalid date format, ignore
            }
        }

        if ($to !== '') {
            try {
                $toDate = date('Y-m-d 23:59:59', strtotime($to));
                $query->where('created_at', '<=', $toDate);
            } catch (\Exception $e) {
                // Invalid date format, ignore
            }
        }

        // Get total count
        $total = $query->count();

        // Pagination
        $offset = ($page - 1) * $limit;
        $items = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'actor_name' => $item->actor_name,
                    'actor_role' => $item->actor_role,
                    'action' => $item->action,
                    'created_at' => $item->created_at,
                    'details' => $item->details,
                ];
            });

        return response()->json([
            'items' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    }
}
