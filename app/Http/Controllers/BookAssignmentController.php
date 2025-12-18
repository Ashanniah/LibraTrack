<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookAssignmentController extends BaseController
{
    public function assignAllBooks()
    {
        $user = $this->requireLogin();
        
        $userId = (int)($user['id'] ?? 0);
        
        // Get statistics (always needed)
        $unassignedCount = DB::table('books')->whereNull('added_by')->count();
        $myBooksCount = DB::table('books')->where('added_by', $userId)->count();
        $totalBooks = DB::table('books')->count();
        
        // Handle POST request to assign books
        if (request()->isMethod('post') && request()->input('assign_all') === 'yes') {
            $updated = DB::table('books')
                ->whereNull('added_by')
                ->update(['added_by' => $userId]);
            
            // Refresh counts after assignment
            $unassignedCount = DB::table('books')->whereNull('added_by')->count();
            $myBooksCount = DB::table('books')->where('added_by', $userId)->count();
        }
        
        // Get book lists
        $unassignedBooks = DB::table('books')
            ->whereNull('added_by')
            ->orderBy('created_at', 'desc')
            ->get();
        
        $myBooks = DB::table('books')
            ->where('added_by', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('book-assignment', [
            'user' => $user,
            'unassignedCount' => $unassignedCount,
            'myBooksCount' => $myBooksCount,
            'totalBooks' => $totalBooks,
            'unassignedBooks' => $unassignedBooks,
            'myBooks' => $myBooks,
            'assigned' => request()->isMethod('post') && request()->input('assign_all') === 'yes' ? $updated : 0,
            'success' => request()->isMethod('post') && request()->input('assign_all') === 'yes'
        ]);
    }
}

