<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentController extends BaseController
{
    public function myLoans(Request $request)
    {
        $student = $this->requireLogin();
        $role = strtolower($student['role'] ?? '');

        if (!in_array($role, ['student', 'admin', 'librarian'], true)) {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $studentId = $role === 'student' ? (int)$student['id'] : (int)$request->input('student_id', 0);
        if ($role !== 'student' && $studentId < 1) {
            return response()->json(['ok' => false, 'error' => 'student_id is required'], 400);
        }
        if ($role === 'student') {
            $studentId = (int)$student['id'];
        }

        $status = strtolower(trim($request->input('status', '')));
        $page = max(1, (int)$request->input('page', 1));
        $pageSize = max(1, min(100, (int)$request->input('pagesize', 50)));
        $offset = ($page - 1) * $pageSize;

        $query = DB::table('loans as l')
            ->join('books as b', 'b.id', '=', 'l.book_id')
            ->where('l.student_id', $studentId);

        if ($status === 'returned') {
            $query->where('l.status', 'returned');
        } elseif ($status === 'borrowed') {
            $query->where('l.status', 'borrowed')
                ->where('l.due_at', '>=', now()->format('Y-m-d'));
        } elseif ($status === 'overdue') {
            $query->where('l.status', 'borrowed')
                ->where('l.due_at', '<', now()->format('Y-m-d'));
        }

        $total = $query->count();
        $pages = max(1, (int)ceil($total / $pageSize));

        $items = $query->select([
                'l.id',
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
                'b.cover',
                'b.category'
            ])
            ->orderBy('l.borrowed_at', 'desc')
            ->limit($pageSize)
            ->offset($offset)
            ->get()
            ->map(function($row) {
                $today = now();
                $due = $row->due_at ? \Carbon\Carbon::parse($row->due_at) : null;
                $returned = $row->returned_at ? \Carbon\Carbon::parse($row->returned_at) : null;

                if ($row->status === 'returned') {
                    $row->loan_status = 'returned';
                } elseif ($due && $due->lt($today)) {
                    $row->loan_status = 'overdue';
                } else {
                    $row->loan_status = 'borrowed';
                }

                $row->cover_url = $row->cover ? ("/uploads/covers/".$row->cover) : null;
                $row->days_late = ($row->loan_status === 'overdue' && $due)
                    ? $due->diffInDays($today)
                    : 0;
                $row->due_in_days = ($row->loan_status === 'borrowed' && $due)
                    ? $today->diffInDays($due)
                    : 0;

                return $row;
            });

        return response()->json([
            'ok' => true,
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages
        ]);
    }

    public function getBookBorrowingHistory(Request $request)
    {
        $user = $this->requireRole(['librarian', 'admin']);

        $bookId = (int)$request->input('book_id', 0);
        if ($bookId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Invalid book ID'], 400);
        }

        $history = DB::table('loans as l')
            ->join('users as u', 'u.id', '=', 'l.student_id')
            ->where('l.book_id', $bookId)
            ->select([
                'l.id as loan_id',
                'l.borrowed_at',
                'l.due_at',
                'l.returned_at',
                'l.status',
                'u.id as student_id',
                'u.first_name',
                'u.last_name',
                'u.student_no',
                'u.course',
                'u.year_level',
                'u.section'
            ])
            ->orderBy('l.borrowed_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function($row) {
                $status = $row->status;
                if ($status === 'borrowed' && $row->due_at) {
                    $dueDate = strtotime($row->due_at);
                    $today = strtotime('today');
                    if ($dueDate < $today) {
                        $status = 'overdue';
                    }
                }

                return [
                    'loan_id' => (int)$row->loan_id,
                    'student_id' => (int)$row->student_id,
                    'first_name' => $row->first_name ?? '',
                    'last_name' => $row->last_name ?? '',
                    'student_no' => $row->student_no ?? '',
                    'course' => $row->course ?? '',
                    'year_level' => $row->year_level ?? '',
                    'section' => $row->section ?? '',
                    'borrowed_at' => $row->borrowed_at ?? null,
                    'due_at' => $row->due_at ?? null,
                    'returned_at' => $row->returned_at ?? null,
                    'status' => $status
                ];
            });

        return response()->json([
            'ok' => true,
            'history' => $history
        ]);
    }

    public function notifications(Request $request)
    {
        $student = $this->requireLogin();
        $role = strtolower($student['role'] ?? '');

        if ($role !== 'student') {
            return response()->json(['ok' => false, 'error' => 'Forbidden'], 403);
        }

        $studentId = (int)$student['id'];
        $notifications = [];

        // Try to get notifications from notifications table if it exists and has user_id column
        try {
            $dbNotifications = DB::table('notifications')
                ->where('user_id', $studentId)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function($n) {
                    return [
                        'type' => $n->type ?? 'info',
                        'title' => $n->title ?? 'Notification',
                        'message' => $n->message ?? '',
                        'read' => (bool)($n->read ?? false),
                        'created_at' => $n->created_at ?? now()->toDateTimeString(),
                        'meta' => $n->meta ? json_decode($n->meta, true) : null,
                    ];
                });

            $notifications = array_merge($notifications, $dbNotifications->toArray());
        } catch (\Exception $e) {
            // Notifications table doesn't exist or has different structure, skip it
            // We'll generate notifications from borrow_requests and loans instead
        }

        // Get borrow requests (pending, approved, rejected) as notifications
        $borrowRequests = DB::table('borrow_requests as br')
            ->join('books as b', 'b.id', '=', 'br.book_id')
            ->where('br.student_id', $studentId)
            ->where('br.requested_at', '>=', now()->subDays(30)->toDateTimeString())
            ->select([
                'br.id',
                'br.status',
                'br.approved_at',
                'br.rejected_at',
                'br.requested_at',
                'br.librarian_note',
                'b.title as book_title',
                'b.isbn'
            ])
            ->orderBy('br.requested_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function($r) {
                if ($r->status === 'pending') {
                    return [
                        'type' => 'request-pending',
                        'title' => 'Borrow Request Submitted',
                        'message' => "Awaiting approval: \"{$r->book_title}\"",
                        'read' => false,
                        'created_at' => $r->requested_at ?? now()->toDateTimeString(),
                        'meta' => [
                            'request_id' => $r->id,
                            'book_title' => $r->book_title,
                            'isbn' => $r->isbn,
                        ],
                    ];
                } elseif ($r->status === 'approved' && $r->approved_at) {
                    return [
                        'type' => 'approved',
                        'title' => 'Borrow Request Approved',
                        'message' => "Ready for pick-up: \"{$r->book_title}\"",
                        'read' => false,
                        'created_at' => $r->approved_at ?? now()->toDateTimeString(),
                        'meta' => [
                            'request_id' => $r->id,
                            'book_title' => $r->book_title,
                            'isbn' => $r->isbn,
                            'librarian_note' => $r->librarian_note,
                        ],
                    ];
                } elseif ($r->status === 'rejected' && $r->rejected_at) {
                    return [
                        'type' => 'rejected',
                        'title' => 'Borrow Request Rejected',
                        'message' => $r->librarian_note ?: "Your request for \"{$r->book_title}\" was rejected.",
                        'read' => false,
                        'created_at' => $r->rejected_at ?? now()->toDateTimeString(),
                        'meta' => [
                            'request_id' => $r->id,
                            'book_title' => $r->book_title,
                            'isbn' => $r->isbn,
                        ],
                    ];
                }
                return null;
            })
            ->filter();

        $notifications = array_merge($notifications, $borrowRequests->toArray());

        // Get overdue loans (as notifications)
        $overdueLoans = DB::table('loans as l')
            ->join('books as b', 'b.id', '=', 'l.book_id')
            ->where('l.student_id', $studentId)
            ->where('l.status', 'borrowed')
            ->where('l.due_at', '<', now()->toDateString())
            ->select([
                'l.id as loan_id',
                'l.due_at',
                'b.title as book_title',
                'b.isbn'
            ])
            ->get()
            ->map(function($l) {
                $dueDate = \Carbon\Carbon::parse($l->due_at);
                $daysLate = $dueDate->diffInDays(now());
                return [
                    'type' => 'overdue',
                    'title' => 'Book Overdue',
                    'message' => "\"{$l->book_title}\" is {$daysLate} day(s) overdue.",
                    'read' => false,
                    'created_at' => $l->due_at ?? now()->toDateTimeString(),
                    'meta' => [
                        'loan_id' => $l->loan_id,
                        'book_title' => $l->book_title,
                        'isbn' => $l->isbn,
                        'days_late' => $daysLate,
                    ],
                ];
            });

        $notifications = array_merge($notifications, $overdueLoans->toArray());

        // Get due soon loans (as notifications) - within 3 days
        $dueSoonLoans = DB::table('loans as l')
            ->join('books as b', 'b.id', '=', 'l.book_id')
            ->where('l.student_id', $studentId)
            ->where('l.status', 'borrowed')
            ->where('l.due_at', '>=', now()->toDateString())
            ->where('l.due_at', '<=', now()->addDays(3)->toDateString())
            ->select([
                'l.id as loan_id',
                'l.due_at',
                'b.title as book_title',
                'b.isbn'
            ])
            ->get()
            ->map(function($l) {
                $dueDate = \Carbon\Carbon::parse($l->due_at);
                $daysUntilDue = now()->diffInDays($dueDate, false);
                return [
                    'type' => 'due-soon',
                    'title' => 'Book Due Soon',
                    'message' => "\"{$l->book_title}\" is due in {$daysUntilDue} day(s).",
                    'read' => false,
                    'created_at' => now()->toDateTimeString(),
                    'meta' => [
                        'loan_id' => $l->loan_id,
                        'book_title' => $l->book_title,
                        'isbn' => $l->isbn,
                        'days_left' => $daysUntilDue,
                    ],
                ];
            });

        $notifications = array_merge($notifications, $dueSoonLoans->toArray());

        // Get returned loans (as notifications)
        $returnedLoans = DB::table('loans as l')
            ->join('books as b', 'b.id', '=', 'l.book_id')
            ->where('l.student_id', $studentId)
            ->where('l.status', 'returned')
            ->where('l.returned_at', '>=', now()->subDays(7)->toDateTimeString())
            ->select([
                'l.id as loan_id',
                'l.returned_at',
                'b.title as book_title',
                'b.isbn'
            ])
            ->orderBy('l.returned_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function($l) {
                return [
                    'type' => 'returned',
                    'title' => 'Return Confirmed',
                    'message' => "Returned: \"{$l->book_title}\"",
                    'read' => false,
                    'created_at' => $l->returned_at ?? now()->toDateTimeString(),
                    'meta' => [
                        'loan_id' => $l->loan_id,
                        'book_title' => $l->book_title,
                        'isbn' => $l->isbn,
                    ],
                ];
            });

        $notifications = array_merge($notifications, $returnedLoans->toArray());

        // Sort by created_at descending (use timestamp field if available)
        usort($notifications, function($a, $b) {
            $timeA = strtotime($a['created_at'] ?? $a['timestamp'] ?? 0);
            $timeB = strtotime($b['created_at'] ?? $b['timestamp'] ?? 0);
            return $timeB - $timeA;
        });

        return response()->json([
            'ok' => true,
            'notifications' => $notifications
        ]);
    }
}



