<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BorrowRequestController extends BaseController
{
    public function list(Request $request)
    {
        $user = $this->requireRole(['librarian', 'admin']);

        $status = trim($request->input('status', ''));
        $page = max(1, (int)$request->input('page', 1));
        $pagesize = max(1, min(100, (int)$request->input('pagesize', 20)));

        $query = DB::table('borrow_requests as br')
            ->join('books as b', 'b.id', '=', 'br.book_id')
            ->join('users as u', 'u.id', '=', 'br.student_id');

        // School filtering for librarians
        $currentRole = strtolower($user['role'] ?? '');
        if ($currentRole === 'librarian') {
            $librarianSchoolId = (int)($user['school_id'] ?? 0);
            if ($librarianSchoolId > 0) {
                $query->where('u.school_id', $librarianSchoolId);
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

        if ($status !== '') {
            $query->where('br.status', $status);
        }

        $total = $query->count();
        $pages = max(1, (int)ceil($total / $pagesize));
        $offset = ($page - 1) * $pagesize;

        $items = $query->select([
                'br.id as request_id',
                'br.duration_days',
                'br.status',
                'br.requested_at',
                'br.approved_at',
                'br.rejected_at',
                'br.rejection_reason',
                'br.librarian_note',
                'b.id as book_id',
                'b.title as book_title',
                'b.isbn',
                'b.author',
                'b.category',
                'b.quantity as book_quantity',
                'b.cover',
                'u.id as student_id',
                'u.first_name',
                'u.last_name',
                'u.student_no',
                'u.course',
                'u.year_level',
                'u.section'
            ])
            ->orderBy('br.requested_at', 'desc')
            ->limit($pagesize)
            ->offset($offset)
            ->get()
            ->map(function($row) {
                $row->cover_url = $row->cover ? ("/uploads/covers/".$row->cover) : null;
                $row->student_name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
                return $row;
            });

        return response()->json([
            'ok' => true,
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'pagesize' => $pagesize
        ]);
    }

    public function create(Request $request)
    {
        $user = $this->requireRole(['student']);

        $request->validate([
            'book_id' => 'required|integer|min:1',
            'duration_days' => 'required|in:3,7,14',
        ]);

        $bookId = (int)$request->input('book_id');
        $durationDays = (int)$request->input('duration_days');

        // Check if book exists and has available quantity
        $book = DB::table('books')->where('id', $bookId)->first();
        if (!$book) {
            return response()->json(['ok' => false, 'error' => 'Book not found'], 404);
        }

        if (($book->quantity ?? 0) < 1) {
            return response()->json(['ok' => false, 'error' => 'Book is not available'], 400);
        }

        // Check if student already has a pending request for this book
        if (DB::table('borrow_requests')
            ->where('student_id', $user['id'])
            ->where('book_id', $bookId)
            ->where('status', 'pending')
            ->exists()) {
            return response()->json(['ok' => false, 'error' => 'You already have a pending request for this book'], 409);
        }

        // Create borrow request
        $requestId = DB::table('borrow_requests')->insertGetId([
            'student_id' => $user['id'],
            'book_id' => $bookId,
            'duration_days' => $durationDays,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'request_id' => $requestId,
            'message' => 'Borrow request submitted successfully'
        ]);
    }

    public function approve(Request $request)
    {
        $user = $this->requireRole(['librarian', 'admin']);

        $request->validate([
            'request_id' => 'required|integer|min:1',
            'duration_days' => 'required|in:3,7,14',
            'librarian_note' => 'nullable|string',
        ]);

        $requestId = (int)$request->input('request_id');
        $durationDays = (int)$request->input('duration_days');
        $librarianNote = $request->input('librarian_note', '');

        // Get request details
        $borrowRequest = DB::table('borrow_requests as br')
            ->join('books as b', 'b.id', '=', 'br.book_id')
            ->join('users as u', 'u.id', '=', 'br.student_id')
            ->where('br.id', $requestId)
            ->select('br.*', 'b.quantity as book_quantity', 'u.school_id as student_school_id')
            ->first();

        if (!$borrowRequest) {
            return response()->json(['ok' => false, 'error' => 'Request not found'], 404);
        }

        // School isolation check
        $currentRole = strtolower($user['role'] ?? '');
        if ($currentRole === 'librarian') {
            $librarianSchoolId = (int)($user['school_id'] ?? 0);
            $studentSchoolId = (int)($borrowRequest->student_school_id ?? 0);
            
            if ($librarianSchoolId <= 0) {
                return response()->json(['ok' => false, 'error' => 'No school assigned to librarian'], 403);
            }
            
            if ($studentSchoolId !== $librarianSchoolId) {
                return response()->json(['ok' => false, 'error' => 'You can only approve requests from students in your school'], 403);
            }
        }

        if ($borrowRequest->status !== 'pending') {
            return response()->json(['ok' => false, 'error' => 'Request is not pending'], 400);
        }

        if (($borrowRequest->book_quantity ?? 0) < 1) {
            return response()->json(['ok' => false, 'error' => 'Book is not available'], 400);
        }

        DB::beginTransaction();
        try {
            // Update request status
            DB::table('borrow_requests')
                ->where('id', $requestId)
                ->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'duration_days' => $durationDays,
                    'librarian_note' => $librarianNote,
                    'approved_by' => $user['id'],
                ]);

            // Calculate due date
            $dueDate = now()->addDays($durationDays)->format('Y-m-d');

            // Create loan record
            $studentSchoolId = (int)($borrowRequest->student_school_id ?? 0);
            $loanData = [
                'request_id' => $requestId,
                'student_id' => $borrowRequest->student_id,
                'book_id' => $borrowRequest->book_id,
                'borrowed_at' => now(),
                'due_at' => $dueDate,
                'status' => 'borrowed',
                'created_at' => now(),
            ];

            if ($this->loansHasSchoolId()) {
                $loanData['school_id'] = $studentSchoolId;
            }

            $loanId = DB::table('loans')->insertGetId($loanData);

            // Decrease book quantity
            DB::table('books')
                ->where('id', $borrowRequest->book_id)
                ->decrement('quantity');

            DB::commit();
            return response()->json([
                'ok' => true,
                'loan_id' => $loanId,
                'message' => 'Request approved and book issued successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'error' => 'Failed to approve request: ' . $e->getMessage()], 500);
        }
    }

    public function reject(Request $request)
    {
        $user = $this->requireRole(['librarian', 'admin']);

        $request->validate([
            'request_id' => 'required|integer|min:1',
            'rejection_reason' => 'nullable|string',
        ]);

        $requestId = (int)$request->input('request_id');
        $rejectionReason = $request->input('rejection_reason', '');

        // Get request with student info
        $borrowRequest = DB::table('borrow_requests as br')
            ->join('users as u', 'u.id', '=', 'br.student_id')
            ->where('br.id', $requestId)
            ->select('br.*', 'u.school_id as student_school_id')
            ->first();

        if (!$borrowRequest) {
            return response()->json(['ok' => false, 'error' => 'Request not found'], 404);
        }

        // School isolation check
        $currentRole = strtolower($user['role'] ?? '');
        if ($currentRole === 'librarian') {
            $librarianSchoolId = (int)($user['school_id'] ?? 0);
            $studentSchoolId = (int)($borrowRequest->student_school_id ?? 0);
            
            if ($librarianSchoolId <= 0) {
                return response()->json(['ok' => false, 'error' => 'No school assigned to librarian'], 403);
            }
            
            if ($studentSchoolId !== $librarianSchoolId) {
                return response()->json(['ok' => false, 'error' => 'You can only reject requests from students in your school'], 403);
            }
        }

        if ($borrowRequest->status !== 'pending') {
            return response()->json(['ok' => false, 'error' => 'Request is not pending'], 400);
        }

        DB::table('borrow_requests')
            ->where('id', $requestId)
            ->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_reason' => $rejectionReason,
            ]);

        return response()->json(['ok' => true, 'message' => 'Request rejected successfully']);
    }

    public function countPending()
    {
        $user = $this->requireLogin();

        $query = DB::table('borrow_requests')->where('status', 'pending');

        // School filtering for librarians
        if (strtolower($user['role'] ?? '') === 'librarian') {
            $librarianSchoolId = (int)($user['school_id'] ?? 0);
            if ($librarianSchoolId > 0) {
                $query->join('users', 'users.id', '=', 'borrow_requests.student_id')
                    ->where('users.school_id', $librarianSchoolId);
            } else {
                return response()->json(['ok' => true, 'count' => 0]);
            }
        }

        $count = $query->count();

        return response()->json(['ok' => true, 'count' => $count]);
    }
}







