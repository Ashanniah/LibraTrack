<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BookController extends BaseController
{
    public function list(Request $request)
    {
        $page = max(1, (int)$request->input('page', 1));
        $pagesize = max(1, min(50, (int)$request->input('pagesize', 12)));
        $q = trim($request->input('q', ''));
        $category = trim($request->input('category', ''));

        $query = DB::table('books');

        if ($q !== '') {
            $query->where(function($qry) use ($q) {
                $qry->where('title', 'LIKE', "%{$q}%")
                    ->orWhere('author', 'LIKE', "%{$q}%")
                    ->orWhere('isbn', 'LIKE', "%{$q}%");
            });
        }

        if ($category !== '') {
            $query->where('category', $category);
        }

        $total = $query->count();
        $pages = max(1, (int)ceil($total / $pagesize));
        $offset = ($page - 1) * $pagesize;

        // Check if archived column exists
        $hasArchived = DB::select("SHOW COLUMNS FROM books LIKE 'archived'");
        $archivedField = count($hasArchived) > 0 ? 'COALESCE(archived, 0) AS archived' : '0 AS archived';

        $items = $query->selectRaw("id, title, isbn, author, category, quantity, publisher, date_published, cover, created_at, COALESCE(rating, 0) AS rating, {$archivedField}")
            ->orderBy('created_at', 'desc')
            ->limit($pagesize)
            ->offset($offset)
            ->get()
            ->map(function($item) {
                $item->cover_url = $item->cover ? ("/uploads/covers/".$item->cover) : null;
                $item->published_at = $item->date_published;
                $item->archived = (int)($item->archived ?? 0);
                return $item;
            })
            ->toArray();

        $bookIds = array_column($items, 'id');

        // Calculate borrowed counts (filtered by school for librarians)
        $borrowedCounts = [];
        if (!empty($bookIds)) {
            $currentUser = $this->currentUser();
            $queryBorrowed = DB::table('loans')
                ->whereIn('book_id', $bookIds)
                ->where('status', 'borrowed');

            // School filtering for librarians
            if ($currentUser && strtolower($currentUser['role'] ?? '') === 'librarian') {
                $librarianSchoolId = (int)($currentUser['school_id'] ?? 0);
                if ($librarianSchoolId > 0) {
                    if ($this->loansHasSchoolId()) {
                        $queryBorrowed->where('loans.school_id', $librarianSchoolId);
                    } else {
                        $queryBorrowed->whereExists(function($query) use ($librarianSchoolId) {
                            $query->select(DB::raw(1))
                                ->from('users')
                                ->whereColumn('users.id', 'loans.student_id')
                                ->where('users.school_id', $librarianSchoolId);
                        });
                    }
                }
            }

            $borrowedCounts = $queryBorrowed->selectRaw('book_id, COUNT(*) as count')
                ->groupBy('book_id')
                ->pluck('count', 'book_id')
                ->toArray();
        }

        // Attach borrowed counts
        foreach ($items as &$item) {
            $item->borrowed = $borrowedCounts[$item->id] ?? 0;
        }
        unset($item);

        // Attach is_favorite for logged-in students
        $user = $this->currentUser();
        if ($user && strtolower($user['role'] ?? '') === 'student' && !empty($items)) {
            $ids = array_column($items, 'id');
            $favorites = DB::table('favorites')
                ->where('user_id', $user['id'])
                ->whereIn('book_id', $ids)
                ->pluck('book_id')
                ->toArray();
            
            $favSet = array_flip($favorites);
            foreach ($items as &$item) {
                $item->is_favorite = isset($favSet[$item->id]);
            }
            unset($item);
        } else {
            foreach ($items as &$item) {
                $item->is_favorite = false;
            }
            unset($item);
        }

        return response()->json([
            'ok' => true,
            'page' => $page,
            'pages' => $pages,
            'pagesize' => $pagesize,
            'total' => (int)$total,
            'items' => $items,
        ]);
    }

    public function add(Request $request)
    {
        $user = $this->requireRole(['librarian', 'admin']);

        $request->validate([
            'title' => 'required|string',
            'isbn' => 'required|string',
            'author' => 'required|string',
            'category' => 'required|string',
            'publisher' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'date_published' => 'nullable|date',
            'cover' => 'nullable|image|max:5120',
        ]);

        // Check unique ISBN (normalize by trimming)
        $isbn = trim($request->input('isbn'));
        if (DB::table('books')->whereRaw('TRIM(isbn) = ?', [trim($isbn)])->exists()) {
            return response()->json(['ok' => false, 'error' => 'ISBN already exists'], 409);
        }

        $coverFile = null;
        if ($request->hasFile('cover')) {
            $file = $request->file('cover');
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($file->getMimeType(), $allowed)) {
                return response()->json(['ok' => false, 'error' => 'Invalid cover type'], 400);
            }

            $ext = $file->getClientOriginalExtension();
            $coverFile = 'cover_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . ".{$ext}";
            $file->move(public_path('uploads/covers'), $coverFile);
        }

        try {
            $bookId = DB::table('books')->insertGetId([
                'title' => $request->input('title'),
                'isbn' => trim($request->input('isbn')), // Store trimmed ISBN
                'author' => $request->input('author'),
                'category' => $request->input('category'),
                'quantity' => $request->input('quantity'),
                'publisher' => $request->input('publisher'),
                'date_published' => $request->input('date_published'),
                'cover' => $coverFile,
                'created_at' => now(),
            ]);

            return response()->json([
                'ok' => true,
                'id' => $bookId,
                'cover' => $coverFile,
                'cover_url' => $coverFile ? ("/uploads/covers/".$coverFile) : null
            ]);
        } catch (\Exception $e) {
            if ($coverFile && file_exists(public_path("uploads/covers/{$coverFile}"))) {
                @unlink(public_path("uploads/covers/{$coverFile}"));
            }
            return response()->json(['ok' => false, 'error' => 'Insert failed'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = $this->requireRole(['librarian', 'admin']);

        $book = DB::table('books')->where('id', $id)->first();
        if (!$book) {
            return response()->json(['ok' => false, 'error' => 'Book not found'], 404);
        }

        // Validate with ISBN uniqueness rule that ignores the current book
        $request->validate([
            'title' => 'required|string',
            'isbn' => [
                'required',
                'string',
                Rule::unique('books', 'isbn')->ignore($id), // Ignore current book's ISBN
            ],
            'author' => 'required|string',
            'category' => 'required|string',
            'publisher' => 'required|string',
            'quantity' => 'required|integer|min:0',
            'date_published' => 'nullable|date',
            'cover' => 'nullable|image|max:5120',
        ]);

        // ISBN validation is handled by Laravel's Rule::unique()->ignore($id) above
        // This ensures ISBN is unique except for the current book being updated

        $coverFile = $book->cover;
        if ($request->hasFile('cover')) {
            $file = $request->file('cover');
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($file->getMimeType(), $allowed)) {
                return response()->json(['ok' => false, 'error' => 'Invalid cover type'], 400);
            }

            // Delete old cover
            if ($coverFile && file_exists(public_path("uploads/covers/{$coverFile}"))) {
                @unlink(public_path("uploads/covers/{$coverFile}"));
            }

            $ext = $file->getClientOriginalExtension();
            $coverFile = 'cover_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . ".{$ext}";
            $file->move(public_path('uploads/covers'), $coverFile);
        }

        DB::table('books')->where('id', $id)->update([
            'title' => $request->input('title'),
            'isbn' => trim($request->input('isbn')), // Store trimmed ISBN
            'author' => $request->input('author'),
            'category' => $request->input('category'),
            'quantity' => $request->input('quantity'),
            'publisher' => $request->input('publisher'),
            'date_published' => $request->input('date_published'),
            'cover' => $coverFile,
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Book updated successfully',
            'cover_url' => $coverFile ? ("/uploads/covers/".$coverFile) : null
        ]);
    }

    public function delete($id)
    {
        $user = $this->requireRole(['librarian', 'admin']);

        $book = DB::table('books')->where('id', $id)->first();
        if (!$book) {
            return response()->json(['ok' => false, 'error' => 'Book not found'], 404);
        }

        try {
            // Check if book has active loans (not returned)
            try {
                // Use raw SQL to avoid closure syntax issues
                $activeLoans = DB::selectOne(
                    "SELECT COUNT(*) as count FROM loans 
                     WHERE book_id = ? 
                     AND (status = 'borrowed' OR returned_at IS NULL)",
                    [$id]
                );
                
                if ($activeLoans && $activeLoans->count > 0) {
                    return response()->json([
                        'ok' => false, 
                        'error' => "Cannot delete book: {$activeLoans->count} active loan(s) exist. Please return all loans first."
                    ], 400);
                }
            } catch (\Exception $e) {
                // If loans table doesn't exist or query fails, continue
                // This allows deletion even if loans table structure is different
            }

            // Check if book has pending borrow requests
            try {
                $pendingRequests = DB::table('borrow_requests')
                    ->where('book_id', $id)
                    ->where('status', 'pending')
                    ->count();
                
                if ($pendingRequests > 0) {
                    return response()->json([
                        'ok' => false, 
                        'error' => "Cannot delete book: {$pendingRequests} pending borrow request(s) exist. Please process or reject all requests first."
                    ], 400);
                }
            } catch (\Exception $e) {
                // If borrow_requests table doesn't exist or query fails, continue
            }

            // Delete related records first (if they exist)
            // Note: Foreign keys with CASCADE should handle this, but we do it explicitly for safety
            try {
                DB::table('loans')->where('book_id', $id)->delete();
            } catch (\Exception $e) {
                // Ignore if loans table doesn't exist or has no records
            }

            try {
                DB::table('borrow_requests')->where('book_id', $id)->delete();
            } catch (\Exception $e) {
                // Ignore if borrow_requests table doesn't exist or has no records
            }

            try {
                DB::table('favorites')->where('book_id', $id)->delete();
            } catch (\Exception $e) {
                // Ignore if favorites table doesn't exist or has no records
            }

            // Delete cover file
            if ($book->cover && file_exists(public_path("uploads/covers/{$book->cover}"))) {
                @unlink(public_path("uploads/covers/{$book->cover}"));
            }

            // Delete the book
            $deleted = DB::table('books')->where('id', $id)->delete();
            
            if (!$deleted) {
                return response()->json(['ok' => false, 'error' => 'Failed to delete book'], 500);
            }

            return response()->json(['ok' => true, 'message' => 'Book deleted successfully']);
        } catch (\Exception $e) {
            // Log error without using Log facade
            error_log('Book delete error: ' . $e->getMessage());
            return response()->json([
                'ok' => false, 
                'error' => 'Failed to delete book: ' . $e->getMessage()
            ], 500);
        }
    }
}






