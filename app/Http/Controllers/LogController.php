<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogController extends BaseController
{
    public function list(Request $request)
    {
        $user = $this->requireRole(['librarian', 'admin']);

        $page = max(1, (int)$request->input('page', 1));
        $pagesize = max(1, min(100, (int)$request->input('pagesize', 20)));

        $query = DB::table('logs');

        // School filtering for librarians
        $currentRole = strtolower($user['role'] ?? '');
        if ($currentRole === 'librarian') {
            $librarianSchoolId = (int)($user['school_id'] ?? 0);
            if ($librarianSchoolId > 0) {
                // Check if logs table has school_id column
                try {
                    $columns = DB::select("SHOW COLUMNS FROM logs LIKE 'school_id'");
                    if (count($columns) > 0) {
                        $query->where('school_id', $librarianSchoolId);
                    }
                } catch (\Exception $e) {
                    // Column doesn't exist, no filtering
                }
            } else {
                return response()->json([
                    'ok' => true,
                    'items' => [],
                    'total' => 0,
                    'page' => $page,
                    'pages' => 0,
                    'pagesize' => $pagesize
                ]);
            }
        }

        $total = $query->count();
        $pages = max(1, (int)ceil($total / $pagesize));
        $offset = ($page - 1) * $pagesize;

        $items = $query->orderBy('created_at', 'desc')
            ->limit($pagesize)
            ->offset($offset)
            ->get();

        return response()->json([
            'ok' => true,
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'pagesize' => $pagesize
        ]);
    }
}







