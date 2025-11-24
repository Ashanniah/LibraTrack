<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends BaseController
{
    public function list(Request $request)
    {
        $user = $this->requireRole(['librarian', 'admin']);

        $status = trim($request->input('status', ''));
        $overdueOnly = $request->has('overdue_only') && $request->input('overdue_only') === '1';
        $activeOnly = $request->has('active_only') && $request->input('active_only') === '1';
        $page = max(1, (int)$request->input('page', 1));
        $pagesize = max(1, min(100, (int)$request->input('pagesize', 20)));

        $query = DB::table('loans as l')
            ->join('books as b', 'b.id', '=', 'l.book_id')
            ->join('users as u', 'u.id', '=', 'l.student_id');

        // School filtering for librarians
        $currentRole = strtolower($user['role'] ?? '');
        if ($currentRole === 'librarian') {
            $librarianSchoolId = (int)($user['school_id'] ?? 0);
            if ($librarianSchoolId > 0) {
                if ($this->loansHasSchoolId()) {
                    $query->where('l.school_id', $librarianSchoolId);
                } else {
                    $query->where('u.school_id', $librarianSchoolId);
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

        // Active only filter
        if ($activeOnly) {
            $query->where('l.status', 'borrowed')
                ->whereNull('l.returned_at');
        }

        // Overdue only filter
        if ($overdueOnly) {
            $query->where('l.due_at', '<', now()->format('Y-m-d'))
                ->where('l.status', 'borrowed')
                ->whereNull('l.returned_at');
        }

        // Status filter
        if ($status !== '' && !$activeOnly) {
            $query->where('l.status', $status);
        }

        $total = $query->count();
        $pages = max(1, (int)ceil($total / $pagesize));
        $offset = ($page - 1) * $pagesize;

        $items = $query->select([
                'l.id as loan_id',
                'l.request_id',
                'l.borrowed_at',
                'l.due_at',
                'l.returned_at',
                'l.status',
                'l.extended_due_at',
                'b.id as book_id',
                'b.title as book_title',
                'b.isbn',
                'b.author',
                'b.category',
                'b.cover',
                'u.id as student_id',
                'u.first_name',
                'u.last_name',
                'u.student_no',
                'u.course',
                'u.year_level',
                'u.section'
            ])
            ->orderBy('l.borrowed_at', 'desc')
            ->limit($pagesize)
            ->offset($offset)
            ->get()
            ->map(function($row) {
                $row->cover_url = $row->cover ? ("/uploads/covers/".$row->cover) : null;
                $row->student_name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
                
                // Calculate loan status
                if ($row->status === 'returned' || !empty($row->returned_at)) {
                    $row->loan_status = 'returned';
                } elseif ($row->status === 'borrowed' && empty($row->returned_at)) {
                    $dueDate = $row->extended_due_at ?? $row->due_at;
                    if ($dueDate && $dueDate < now()->format('Y-m-d')) {
                        $row->loan_status = 'overdue';
                    } else {
                        $row->loan_status = 'on-time';
                    }
                } else {
                    $row->loan_status = 'borrowed';
                }
                
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
        $user = $this->requireRole(['librarian', 'admin']);

        $request->validate([
            'student_id' => 'required|integer|min:1',
            'book_id' => 'required|integer|min:1',
            'borrowed_at' => 'nullable|date',
            'due_at' => 'required|date',
        ]);

        $studentId = (int)$request->input('student_id');
        $bookId = (int)$request->input('book_id');
        $borrowedAt = $request->input('borrowed_at') ?: now();
        $dueAt = $request->input('due_at');

        // Validate dates
        if (strtotime($dueAt) <= strtotime($borrowedAt)) {
            return response()->json(['ok' => false, 'error' => 'Due date must be after borrowed date'], 400);
        }

        // Check student exists
        $student = DB::table('users')
            ->where('id', $studentId)
            ->where('role', 'student')
            ->first();

        if (!$student) {
            return response()->json(['ok' => false, 'error' => 'Student not found'], 404);
        }

        // School isolation check
        $currentRole = strtolower($user['role'] ?? '');
        if ($currentRole === 'librarian') {
            $librarianSchoolId = (int)($user['school_id'] ?? 0);
            $studentSchoolId = (int)($student->school_id ?? 0);
            
            if ($librarianSchoolId <= 0) {
                return response()->json(['ok' => false, 'error' => 'No school assigned to librarian'], 403);
            }
            
            if ($studentSchoolId !== $librarianSchoolId) {
                return response()->json(['ok' => false, 'error' => 'You can only create loans for students in your school'], 403);
            }
        }

        // Check book exists and availability
        $book = DB::table('books')->where('id', $bookId)->first();
        if (!$book) {
            return response()->json(['ok' => false, 'error' => 'Book not found'], 404);
        }

        $borrowedCount = DB::table('loans')
            ->where('book_id', $bookId)
            ->where('status', 'borrowed')
            ->count();

        $available = (int)($book->quantity ?? 0) - $borrowedCount;
        if ($available < 1) {
            return response()->json(['ok' => false, 'error' => 'Book is not available (no copies left)'], 400);
        }

        // Create loan
        $studentSchoolId = (int)($student->school_id ?? 0);
        $loanData = [
            'student_id' => $studentId,
            'book_id' => $bookId,
            'borrowed_at' => $borrowedAt,
            'due_at' => $dueAt,
            'status' => 'borrowed',
            'created_at' => now(),
        ];

        if ($this->loansHasSchoolId()) {
            $loanData['school_id'] = $studentSchoolId;
        }

        $loanId = DB::table('loans')->insertGetId($loanData);

        return response()->json([
            'ok' => true,
            'message' => 'Loan created successfully',
            'loan_id' => $loanId
        ]);
    }

    public function returnBook(Request $request)
    {
        $user = $this->requireRole(['librarian', 'admin']);

        $request->validate([
            'loan_id' => 'required|integer|min:1',
            'returned_at' => 'nullable|date',
        ]);

        $loanId = (int)$request->input('loan_id');
        $returnedAt = $request->input('returned_at') ?: now();

        // Get loan with student info
        $loan = DB::table('loans as l')
            ->join('books as b', 'b.id', '=', 'l.book_id')
            ->join('users as u', 'u.id', '=', 'l.student_id')
            ->where('l.id', $loanId)
            ->select('l.*', 'b.id as book_id', 'u.school_id as student_school_id')
            ->first();

        if (!$loan) {
            return response()->json(['ok' => false, 'error' => 'Loan not found'], 404);
        }

        // School isolation check
        $currentRole = strtolower($user['role'] ?? '');
        if ($currentRole === 'librarian') {
            $librarianSchoolId = (int)($user['school_id'] ?? 0);
            $loanSchoolId = 0;
            
            if ($this->loansHasSchoolId()) {
                $loanSchoolId = (int)($loan->school_id ?? 0);
            } else {
                $loanSchoolId = (int)($loan->student_school_id ?? 0);
            }
            
            if ($librarianSchoolId <= 0) {
                return response()->json(['ok' => false, 'error' => 'No school assigned to librarian'], 403);
            }
            
            if ($loanSchoolId !== $librarianSchoolId) {
                return response()->json(['ok' => false, 'error' => 'You can only return books for students in your school'], 403);
            }
        }

        if ($loan->status === 'returned') {
            return response()->json(['ok' => false, 'error' => 'Book is already returned'], 400);
        }

        DB::beginTransaction();
        try {
            DB::table('loans')
                ->where('id', $loanId)
                ->update([
                    'status' => 'returned',
                    'returned_at' => $returnedAt
                ]);

            DB::table('books')
                ->where('id', $loan->book_id)
                ->increment('quantity');

            DB::commit();
            return response()->json(['ok' => true, 'message' => 'Book marked as returned successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'error' => 'Failed to return book: ' . $e->getMessage()], 500);
        }
    }

    public function extendDueDate(Request $request)
    {
        $user = $this->requireRole(['librarian', 'admin']);

        $request->validate([
            'loan_id' => 'required|integer|min:1',
            'extended_due_at' => 'required|date',
        ]);

        $loanId = (int)$request->input('loan_id');
        $extendedDueAt = $request->input('extended_due_at');

        // Get loan with student info
        $loan = DB::table('loans as l')
            ->join('users as u', 'u.id', '=', 'l.student_id')
            ->where('l.id', $loanId)
            ->select('l.*', 'u.school_id as student_school_id')
            ->first();

        if (!$loan) {
            return response()->json(['ok' => false, 'error' => 'Loan not found'], 404);
        }

        // School isolation check
        $currentRole = strtolower($user['role'] ?? '');
        if ($currentRole === 'librarian') {
            $librarianSchoolId = (int)($user['school_id'] ?? 0);
            $loanSchoolId = 0;
            
            if ($this->loansHasSchoolId()) {
                $loanSchoolId = (int)($loan->school_id ?? 0);
            } else {
                $loanSchoolId = (int)($loan->student_school_id ?? 0);
            }
            
            if ($librarianSchoolId <= 0) {
                return response()->json(['ok' => false, 'error' => 'No school assigned to librarian'], 403);
            }
            
            if ($loanSchoolId !== $librarianSchoolId) {
                return response()->json(['ok' => false, 'error' => 'You can only extend due dates for loans from your school'], 403);
            }
        }

        DB::table('loans')
            ->where('id', $loanId)
            ->update(['extended_due_at' => $extendedDueAt]);

        return response()->json(['ok' => true, 'message' => 'Due date extended successfully']);
    }

    public function delete($id)
    {
        $user = $this->requireRole(['librarian', 'admin']);

        $loan = DB::table('loans as l')
            ->join('users as u', 'u.id', '=', 'l.student_id')
            ->where('l.id', $id)
            ->select('l.*', 'u.school_id as student_school_id')
            ->first();

        if (!$loan) {
            return response()->json(['ok' => false, 'error' => 'Loan not found'], 404);
        }

        // School isolation check
        $currentRole = strtolower($user['role'] ?? '');
        if ($currentRole === 'librarian') {
            $librarianSchoolId = (int)($user['school_id'] ?? 0);
            $loanSchoolId = 0;
            
            if ($this->loansHasSchoolId()) {
                $loanSchoolId = (int)($loan->school_id ?? 0);
            } else {
                $loanSchoolId = (int)($loan->student_school_id ?? 0);
            }
            
            if ($librarianSchoolId <= 0 || $loanSchoolId !== $librarianSchoolId) {
                return response()->json(['ok' => false, 'error' => 'You can only delete loans from your school'], 403);
            }
        }

        DB::table('loans')->where('id', $id)->delete();

        return response()->json(['ok' => true, 'message' => 'Loan deleted successfully']);
    }
}









