<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BookController extends BaseController
{
    public function show($id)
    {
        $book = DB::table('books')->where('id', $id)->first();
        
        if (!$book) {
            return response()->json(['ok' => false, 'error' => 'Book not found'], 404);
        }
        
        // Format book data similar to list method
        $bookData = [
            'id' => $book->id,
            'title' => $book->title,
            'isbn' => $book->isbn,
            'author' => $book->author,
            'category' => $book->category,
            'quantity' => (int)($book->quantity ?? 0), // Ensure quantity is an integer
            'publisher' => $book->publisher,
            'date_published' => $book->date_published,
            'published_at' => $book->date_published,
            'cover' => $book->cover,
            'cover_url' => $book->cover ? ("/uploads/covers/".$book->cover) : null,
            'created_at' => $book->created_at,
            'rating' => (int)($book->rating ?? 0),
        ];
        
        return response()->json([
            'ok' => true,
            'data' => $bookData
        ]);
    }

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
        try {
            $user = $this->requireRole(['librarian', 'admin']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => 'Authentication failed: ' . $e->getMessage()], 401);
        }

        try {
            $book = DB::table('books')->where('id', $id)->first();
            if (!$book) {
                return response()->json(['ok' => false, 'error' => 'Book not found'], 404);
            }

            // Validate fields - all fields are optional for updates (librarian can update any field they want)
            // ISBN and date_published are NOT included - they cannot be changed when updating
            // Explicitly remove them from the request to prevent any validation issues
            $request->request->remove('isbn');
            $request->request->remove('date_published');
            
            try {
                $request->validate([
                    'title' => 'nullable|string',
                    // ISBN is intentionally excluded - it cannot be edited
                    'author' => 'nullable|string',
                    'category' => 'nullable|string',
                    'publisher' => 'nullable|string',
                    'quantity' => 'nullable|integer|min:0',
                    // date_published is intentionally excluded - it cannot be edited
                    'cover' => 'nullable|image|max:5120',
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                $errors = $e->errors();
                $errorMessages = [];
                foreach ($errors as $field => $messages) {
                    $errorMessages[] = implode(', ', $messages);
                }
                return response()->json([
                    'ok' => false,
                    'error' => implode(' ', $errorMessages),
                    'errors' => $errors
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
        }

        try {
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

            // Update book fields - only update fields that are provided and have values
            // NOTE: ISBN, date_published, and created_at are intentionally excluded - they cannot be changed
            // created_at is the system audit date and should NEVER be modified
            // date_published is set when the book is first added and should not be changed
            // Note: updated_at column doesn't exist in the books table, so we don't update it
            $updateData = [];
            
            // Only update fields that are provided and have non-empty values
            // FormData always sends fields, so we check if they have actual values
            if ($request->filled('title')) {
                $updateData['title'] = trim($request->input('title'));
            }
            if ($request->filled('author')) {
                $updateData['author'] = trim($request->input('author'));
            }
            if ($request->filled('category')) {
                $updateData['category'] = trim($request->input('category'));
            }
            if ($request->filled('publisher')) {
                $updateData['publisher'] = trim($request->input('publisher'));
            }
            // Handle quantity - accept valid integers including 0
            if ($request->has('quantity')) {
                $quantityInput = $request->input('quantity');
                // Accept quantity if it's a valid numeric value (including 0 and empty string "0")
                if ($quantityInput !== null && $quantityInput !== '' && (is_numeric($quantityInput) || $quantityInput === '0')) {
                    $updateData['quantity'] = intval($quantityInput);
                } elseif ($quantityInput === '0' || $quantityInput === 0) {
                    // Explicitly handle 0
                    $updateData['quantity'] = 0;
                }
            }
            // ISBN is intentionally excluded - it cannot be changed
            // date_published is intentionally excluded - it cannot be changed
            if ($coverFile) {
                $updateData['cover'] = $coverFile;
            }
            // created_at is NEVER updated - it's the system audit date
            
            // Only update if there are fields to update
            if (!empty($updateData)) {
                DB::table('books')->where('id', $id)->update($updateData);
                
                // Verify the update by fetching the updated book
                $updatedBook = DB::table('books')->where('id', $id)->first();
                
                return response()->json([
                    'ok' => true,
                    'message' => 'Book updated successfully',
                    'cover_url' => $coverFile ? ("/uploads/covers/".$coverFile) : null,
                    'updated_data' => [
                        'quantity' => (int)($updatedBook->quantity ?? 0),
                        'publisher' => $updatedBook->publisher ?? null,
                        'title' => $updatedBook->title ?? null
                    ]
                ]);
            } else {
                return response()->json([
                    'ok' => false,
                    'error' => 'No fields to update'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }

    public function delete($id)
    {
        try {
            $user = $this->requireRole(['librarian', 'admin']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => 'Authentication failed: ' . $e->getMessage()], 401);
        }

        try {
            $book = DB::table('books')->where('id', $id)->first();
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => 'Database connection error: ' . $e->getMessage()], 500);
        }
        
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






