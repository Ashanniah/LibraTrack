<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Helpers\IsbnValidator;
use App\Helpers\AuditLog;
use App\Helpers\NotificationHelper;

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

        // Librarian-level isolation - librarians only see books they added
        $currentUser = $this->currentUser();
        if ($currentUser) {
            $userRole = strtolower($currentUser['role'] ?? '');
            $userId = (int)($currentUser['id'] ?? 0);
            
            // Filter by added_by for librarians - they only see books they added
            if ($userRole === 'librarian') {
                $hasAddedBy = $this->booksHasAddedBy();
                if ($hasAddedBy) {
                    // Librarians only see books they added
                    $query->where('books.added_by', $userId);
                } else {
                    // If column doesn't exist yet, show all books for librarians
                    // This allows them to see books until the column is added
                    // Once column is added, filtering will work properly
                }
            } elseif ($userRole === 'student') {
                // Students see ONLY books from their school (strict school-based filtering)
                $userSchoolId = (int)($currentUser['school_id'] ?? 0);
                if ($userSchoolId > 0) {
                    $hasSchoolId = $this->booksHasSchoolId();
                    if ($hasSchoolId) {
                        // Students ONLY see books from their school (no NULL books)
                        // This ensures students only see books added by librarians from their school
                        $query->where('books.school_id', $userSchoolId);
                    }
                } else {
                    // Student without school_id - show no books
                    $query->whereRaw('1 = 0'); // Never match anything
                }
            }
        }
        // Admin and public users see all books (no filter)

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
            // Use the same $currentUser we already fetched above
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

        // Comprehensive ISBN Validation
        $rawIsbn = $request->input('isbn');
        $isbnValidation = IsbnValidator::validate($rawIsbn);
        
        if (!$isbnValidation['valid']) {
            return response()->json(['ok' => false, 'error' => $isbnValidation['error']], 422);
        }

        // Use normalized ISBN for uniqueness check and storage
        $normalizedIsbn = $isbnValidation['cleaned'];
        
        // Check unique ISBN only for books added by the current user
        // Only show error if the user themselves added a book with the exact same 13-digit ISBN
        try {
            $userId = (int)($user['id'] ?? 0);
            $userRole = strtolower($user['role'] ?? '');
            
            $isbnQuery = DB::table('books')->whereRaw('TRIM(REPLACE(REPLACE(isbn, "-", ""), " ", "")) = ?', [$normalizedIsbn]);
            
            // Only check against books added by the current user (for librarians)
            // Admin can have duplicates from different librarians, but not from themselves
            if ($userRole === 'librarian' || $userRole === 'admin') {
                if ($this->booksHasAddedBy() && $userId > 0) {
                    $isbnQuery->where('added_by', $userId);
                }
            }
            // If added_by column doesn't exist or user is not librarian/admin, check globally (fallback)
            
            if ($isbnQuery->exists()) {
                return response()->json(['ok' => false, 'error' => 'A book with this ISBN already exists.'], 409);
            }
        } catch (\Exception $e) {
            // If ISBN check fails, log but don't block - allow the insert to proceed
            error_log("ISBN duplicate check error: " . $e->getMessage());
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
            $insertData = [
                'title' => $request->input('title'),
                'isbn' => $normalizedIsbn, // Store normalized ISBN (cleaned, validated)
                'author' => $request->input('author'),
                'category' => $request->input('category'),
                'quantity' => $request->input('quantity'),
                'publisher' => $request->input('publisher'),
                'date_published' => $request->input('date_published'),
                'cover' => $coverFile,
                'created_at' => now(),
            ];

            // Set added_by for librarians (track who added the book)
            if (strtolower($user['role'] ?? '') === 'librarian') {
                $userId = (int)($user['id'] ?? 0);
                if ($userId > 0) {
                    // Always try to set added_by if column exists
                    if ($this->booksHasAddedBy()) {
                        $insertData['added_by'] = $userId;
                        // Log for debugging (remove in production if not needed)
                        error_log("BookController@add: Setting added_by = {$userId} for user {$user['email']} (ID: {$user['id']})");
                    } else {
                        error_log("BookController@add: WARNING - booksHasAddedBy() returned false, added_by not set!");
                    }
                } else {
                    error_log("BookController@add: WARNING - userId is 0 or invalid for user: " . ($user['email'] ?? 'unknown'));
                }
                // ALWAYS set school_id for librarians (REQUIRED for student visibility)
                $librarianSchoolId = (int)($user['school_id'] ?? 0);
                if ($librarianSchoolId > 0) {
                    if ($this->booksHasSchoolId()) {
                        $insertData['school_id'] = $librarianSchoolId;
                        error_log("BookController@add: Setting school_id = {$librarianSchoolId} for librarian {$user['email']}");
                    } else {
                        error_log("BookController@add: WARNING - booksHasSchoolId() returned false, school_id not set! Students won't see this book.");
                    }
                } else {
                    error_log("BookController@add: WARNING - Librarian {$user['email']} has no school_id! Students won't see books added by this librarian.");
                }
            }
            // Admin can leave added_by and school_id null for global books

            try {
                $bookId = DB::table('books')->insertGetId($insertData);
            } catch (\Illuminate\Database\QueryException $e) {
                // Handle database constraint violations (like unique ISBN)
                $errorCode = $e->getCode();
                $errorMessage = $e->getMessage();
                
                // MySQL error code 1062 is "Duplicate entry", 23000 is SQL standard
                if ($errorCode == 23000 || $errorCode == 1062 || 
                    strpos($errorMessage, 'Duplicate entry') !== false || 
                    strpos($errorMessage, 'UNIQUE constraint') !== false ||
                    stripos($errorMessage, 'unique constraint') !== false) {
                    
                    // Check if this is an ISBN duplicate
                    if (stripos($errorMessage, 'isbn') !== false) {
                        // Check if the duplicate is from the same user
                        $duplicateBook = DB::table('books')
                            ->whereRaw('TRIM(REPLACE(REPLACE(isbn, "-", ""), " ", "")) = ?', [$normalizedIsbn])
                            ->first();
                        
                        if ($duplicateBook) {
                            $duplicateAddedBy = (int)($duplicateBook->added_by ?? 0);
                            $currentUserId = (int)($user['id'] ?? 0);
                            
                            // Only show error if the duplicate is from the same user
                            if ($duplicateAddedBy === $currentUserId && $currentUserId > 0 && $this->booksHasAddedBy()) {
                                return response()->json(['ok' => false, 'error' => 'A book with this ISBN already exists.'], 409);
                            } else {
                                // Different user has this ISBN - database unique constraint prevents this
                                // This means the database has a global unique constraint on ISBN
                                // We need to inform the user that ISBN must be globally unique
                                return response()->json([
                                    'ok' => false, 
                                    'error' => 'A book with this ISBN already exists in the system.'
                                ], 409);
                            }
                        }
                        return response()->json(['ok' => false, 'error' => 'A book with this ISBN already exists.'], 409);
                    }
                }
                // Re-throw if it's not a constraint violation we can handle
                throw $e;
            }
            
            // Log book creation
            AuditLog::logAction(
                $user,
                'BOOK_CREATE',
                'book',
                $bookId,
                "Created book: {$request->input('title')} (ISBN: {$normalizedIsbn})"
            );
            
            // Check for low stock on new book creation
            try {
                $quantity = (int)$request->input('quantity');
                $lowStockThreshold = (int)(DB::table('settings')->where('key', 'low_stock_threshold')->value('value') ?? 5);
                
                if ($quantity <= $lowStockThreshold) {
                    // Notify librarians about low stock
                    $librariansQuery = DB::table('users')
                        ->where('role', 'librarian')
                        ->where('status', 'active');
                    
                    $librarianSchoolId = (int)($user['school_id'] ?? 0);
                    if ($librarianSchoolId > 0) {
                        $librariansQuery->where('school_id', $librarianSchoolId);
                    }
                    
                    $librarians = $librariansQuery->get();
                    
                    foreach ($librarians as $librarian) {
                        try {
                            // Queue email notification (REQUIRED for LOW_STOCK)
                            // In-app notification created automatically when email is sent successfully
                            NotificationHelper::queueEmail(
                                $librarian->id,
                                'LOW_STOCK',
                                'book',
                                $bookId
                            );
                        } catch (\Exception $e) {
                            error_log("Failed to queue low stock email: " . $e->getMessage());
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignore low stock check errors
            }
            
            // Verify added_by was set correctly (for debugging)
            if (strtolower($user['role'] ?? '') === 'librarian' && $this->booksHasAddedBy()) {
                $insertedBook = DB::table('books')->where('id', $bookId)->first();
                $actualAddedBy = $insertedBook->added_by ?? null;
                if ($actualAddedBy != $userId) {
                    error_log("BookController@add: ERROR - Book ID {$bookId} has added_by = {$actualAddedBy}, expected {$userId}");
                }
            }

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
            // Log the actual error for debugging
            error_log("Book insert error: " . $e->getMessage());
            error_log("Book insert error trace: " . $e->getTraceAsString());
            return response()->json([
                'ok' => false, 
                'error' => 'Failed to add book: ' . $e->getMessage()
            ], 500);
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

            // Librarian ownership validation - librarians can only update books they added
            if (strtolower($user['role'] ?? '') === 'librarian') {
                $userId = (int)($user['id'] ?? 0);
                if ($userId > 0 && $this->booksHasAddedBy()) {
                    $bookAddedBy = (int)($book->added_by ?? 0);
                    if ($bookAddedBy !== $userId) {
                        return response()->json(['ok' => false, 'error' => 'You can only update books you added'], 403);
                    }
                }
            }
            // Admin can update any book

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

            // Update book fields - update ALL editable fields (Title, Author, Category, Publisher, Quantity)
            // NOTE: ISBN, date_published, and created_at are intentionally excluded - they cannot be changed
            // created_at is the system audit date and should NEVER be modified
            // date_published is set when the book is first added and should not be changed
            // Note: updated_at column doesn't exist in the books table, so we don't update it
            $updateData = [];
            
            // Debug: Log all request inputs - check both all() and input() methods
            error_log('BookController@update: Request method: ' . $request->method());
            error_log('BookController@update: Request all(): ' . json_encode($request->all()));
            error_log('BookController@update: Request input(): ' . json_encode($request->input()));
            error_log('BookController@update: Request has title: ' . ($request->has('title') ? 'yes' : 'no'));
            error_log('BookController@update: Request has category: ' . ($request->has('category') ? 'yes' : 'no'));
            error_log('BookController@update: Request has quantity: ' . ($request->has('quantity') ? 'yes' : 'no'));
            
            // For PUT requests with FormData, Laravel may not parse it correctly
            // Try to get data from request->all() first, then fall back to input()
            $allInputs = $request->all();
            
            // Update ALL editable fields - Title, Author, Category, Publisher, Quantity
            // These fields are always sent from the frontend when editing
            if (isset($allInputs['title']) || $request->has('title')) {
                $titleValue = trim($allInputs['title'] ?? $request->input('title', ''));
                if ($titleValue !== '') {
                    $updateData['title'] = $titleValue;
                    error_log('BookController@update: Setting title = ' . $updateData['title']);
                }
            }
            if (isset($allInputs['author']) || $request->has('author')) {
                $authorValue = trim($allInputs['author'] ?? $request->input('author', ''));
                if ($authorValue !== '') {
                    $updateData['author'] = $authorValue;
                    error_log('BookController@update: Setting author = ' . $updateData['author']);
                }
            }
            if (isset($allInputs['category']) || $request->has('category')) {
                $categoryValue = trim($allInputs['category'] ?? $request->input('category', ''));
                if ($categoryValue !== '') {
                    $updateData['category'] = $categoryValue;
                    error_log('BookController@update: Setting category = ' . $updateData['category']);
                }
            }
            if (isset($allInputs['publisher']) || $request->has('publisher')) {
                $publisherValue = trim($allInputs['publisher'] ?? $request->input('publisher', ''));
                if ($publisherValue !== '') {
                    $updateData['publisher'] = $publisherValue;
                    error_log('BookController@update: Setting publisher = ' . $updateData['publisher']);
                }
            }
            // Handle quantity - accept valid integers including 0
            if (isset($allInputs['quantity']) || $request->has('quantity')) {
                $quantityInput = $allInputs['quantity'] ?? $request->input('quantity');
                error_log('BookController@update: Quantity input = ' . var_export($quantityInput, true));
                // Accept quantity if it's a valid numeric value (including 0)
                if ($quantityInput !== null && $quantityInput !== '' && is_numeric($quantityInput)) {
                    $updateData['quantity'] = intval($quantityInput);
                    error_log('BookController@update: Setting quantity = ' . $updateData['quantity']);
                } elseif ($quantityInput === '0' || $quantityInput === 0) {
                    // Explicitly handle 0
                    $updateData['quantity'] = 0;
                    error_log('BookController@update: Setting quantity = 0');
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
                
                // Log book update
                $updatedFields = implode(', ', array_keys($updateData));
                AuditLog::logAction(
                    $user,
                    'BOOK_UPDATE',
                    'book',
                    $id,
                    "Updated book ID {$id}: {$updatedFields}"
                );
                
                // Verify the update by fetching the updated book
                $updatedBook = DB::table('books')->where('id', $id)->first();
                
                // Check for low stock if quantity was updated
                if (isset($updateData['quantity'])) {
                    try {
                        $newQuantity = (int)($updatedBook->quantity ?? 0);
                        $oldQuantity = (int)($book->quantity ?? 0);
                        $lowStockThreshold = (int)(DB::table('settings')->where('key', 'low_stock_threshold')->value('value') ?? 5);
                        
                        // Only notify if crossing threshold (was above, now at or below)
                        if ($oldQuantity > $lowStockThreshold && $newQuantity <= $lowStockThreshold) {
                            // Get school_id for filtering librarians
                            $bookSchoolId = (int)($updatedBook->school_id ?? 0);
                            
                            // Notify librarians
                            $librariansQuery = DB::table('users')
                                ->where('role', 'librarian')
                                ->where('status', 'active');
                            
                            if ($bookSchoolId > 0) {
                                $librariansQuery->where('school_id', $bookSchoolId);
                            }
                            
                            $librarians = $librariansQuery->get();
                            
                            foreach ($librarians as $librarian) {
                                try {
                                    NotificationHelper::create(
                                        $librarian->id,
                                        'librarian',
                                        'LOW_STOCK',
                                        'Low stock alert',
                                        "{$updatedBook->title} has reached the low-stock threshold ({$newQuantity} copies left).",
                                        'book',
                                        $id,
                                        'librarian-lowstock.html'
                                    );
                                    
                                    // Queue email (REQUIRED for LOW_STOCK)
                                    try {
                                        require_once __DIR__ . '/../../includes/email_notifications.php';
                                        $pdo = DB::connection()->getPdo();
                                        queue_email($pdo, $librarian->id, 'LOW_STOCK', 'book', $id);
                                    } catch (\Exception $e) {
                                        error_log("Failed to queue low stock email: " . $e->getMessage());
                                    }
                                } catch (\Exception $e) {
                                    // Continue with other librarians
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignore low stock check errors
                        error_log("Low stock check error: " . $e->getMessage());
                    }
                }
                
                // Return full updated book data for frontend refresh
                $bookData = [
                    'id' => $updatedBook->id,
                    'title' => $updatedBook->title,
                    'isbn' => $updatedBook->isbn,
                    'author' => $updatedBook->author,
                    'category' => $updatedBook->category,
                    'quantity' => (int)($updatedBook->quantity ?? 0),
                    'publisher' => $updatedBook->publisher,
                    'date_published' => $updatedBook->date_published,
                    'published_at' => $updatedBook->date_published,
                    'cover' => $updatedBook->cover,
                    'cover_url' => $updatedBook->cover ? ("/uploads/covers/".$updatedBook->cover) : null,
                    'created_at' => $updatedBook->created_at,
                    'rating' => (int)($updatedBook->rating ?? 0),
                ];
                
                return response()->json([
                    'ok' => true,
                    'message' => 'Book updated successfully',
                    'cover_url' => $coverFile ? ("/uploads/covers/".$coverFile) : null,
                    'data' => $bookData, // Return full book data for frontend refresh
                    'updated_data' => [
                        'quantity' => (int)($updatedBook->quantity ?? 0),
                        'publisher' => $updatedBook->publisher ?? null,
                        'title' => $updatedBook->title ?? null,
                        'category' => $updatedBook->category ?? null
                    ],
                    // Debug info to track what was received and applied
                    'request_inputs' => $request->all(),
                    'applied_update_data' => $updateData
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

        // Librarian ownership validation - librarians can only delete books they added
        if (strtolower($user['role'] ?? '') === 'librarian') {
            $userId = (int)($user['id'] ?? 0);
            if ($userId > 0 && $this->booksHasAddedBy()) {
                $bookAddedBy = (int)($book->added_by ?? 0);
                if ($bookAddedBy !== $userId) {
                    return response()->json(['ok' => false, 'error' => 'You can only delete books you added'], 403);
                }
            }
        }
        // Admin can delete any book

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

            // Log book deletion
            AuditLog::logAction(
                $user,
                'BOOK_DELETE',
                'book',
                $id,
                "Deleted book: {$book->title} (ISBN: {$book->isbn})"
            );

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






