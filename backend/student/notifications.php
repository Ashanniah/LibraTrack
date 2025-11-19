<?php
// backend/student/notifications.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$student = require_login($conn);
$role = strtolower((string)($student['role'] ?? ''));
if ($role !== 'student') {
  json_response(['ok'=>false,'error'=>'Forbidden'], 403);
}
$studentId = (int)$student['id'];

$notifications = [];
$now = new DateTimeImmutable();

// Borrow requests (approval / rejection / notes)
$stmt = $conn->prepare("
  SELECT br.id, br.status, br.duration_days, br.librarian_note,
         br.approved_at, br.rejected_at, br.requested_at,
         b.title AS book_title
  FROM borrow_requests br
  INNER JOIN books b ON b.id = br.book_id
  WHERE br.student_id = ?
  ORDER BY br.requested_at DESC
  LIMIT 20
");
$stmt->bind_param('i', $studentId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  if ($row['status'] === 'pending') {
    $notifications[] = [
      'type' => 'request-pending',
      'title' => 'Borrow request submitted',
      'message' => $row['book_title'] ? ("Awaiting approval: {$row['book_title']}") : 'Awaiting librarian approval.',
      'timestamp' => $row['requested_at'],
      'meta' => ['request_id' => $row['id']],
    ];
  } elseif ($row['status'] === 'approved' && $row['approved_at']) {
    $notifications[] = [
      'type' => 'approved',
      'title' => 'Borrow request approved',
      'message' => $row['book_title'] ? ("Ready for pick-up: {$row['book_title']}") : 'Your borrow request has been approved.',
      'timestamp' => $row['approved_at'],
      'meta' => [
        'request_id' => $row['id'],
        'librarian_note' => $row['librarian_note'],
      ],
    ];
  } elseif ($row['status'] === 'rejected' && $row['rejected_at']) {
    $notifications[] = [
      'type' => 'rejected',
      'title' => 'Borrow request rejected',
      'message' => $row['librarian_note'] ?: 'The librarian rejected your request.',
      'timestamp' => $row['rejected_at'],
      'meta' => [
        'request_id' => $row['id'],
      ],
    ];
  }
}
$stmt->close();

// Loans (due soon, overdue, returned)
$stmt = $conn->prepare("
  SELECT l.id, l.borrowed_at, l.due_at, l.returned_at, l.status,
         b.title AS book_title
  FROM loans l
  INNER JOIN books b ON b.id = l.book_id
  WHERE l.student_id = ?
  ORDER BY l.borrowed_at DESC
  LIMIT 40
");
$stmt->bind_param('i', $studentId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  $due = $row['due_at'] ? new DateTimeImmutable($row['due_at']) : null;
  $returned = $row['returned_at'] ? new DateTimeImmutable($row['returned_at']) : null;

  if ($row['status'] === 'returned' && $returned) {
    $notifications[] = [
      'type' => 'returned',
      'title' => 'Return confirmed',
      'message' => $row['book_title'] ? ("Returned: {$row['book_title']}") : 'Thanks! Your borrowed book has been marked as returned.',
      'timestamp' => $row['returned_at'],
      'meta' => ['loan_id' => $row['id']],
    ];
    continue;
  }

  if ($due) {
    if ($due < $now) {
      $daysLate = $due->diff($now)->days;
      $notifications[] = [
        'type' => 'overdue',
        'title' => 'Book overdue',
        'message' => $row['book_title']
          ? "{$row['book_title']} is overdue by {$daysLate} day(s)."
          : "This book is overdue by {$daysLate} day(s).",
        'timestamp' => $due->format('Y-m-d 00:00:00'),
        'meta' => ['loan_id' => $row['id'], 'days_late' => $daysLate],
      ];
    } elseif ($due > $now) {
      $daysLeft = $now->diff($due)->days;
      if ($daysLeft <= 3) {
        $notifications[] = [
          'type' => 'due-soon',
          'title' => 'Book due soon',
          'message' => $row['book_title']
            ? "{$row['book_title']} is due in {$daysLeft} day(s)."
            : 'Your borrowed book is due soon.',
          'timestamp' => $now->format('Y-m-d H:i:s'),
          'meta' => ['loan_id' => $row['id'], 'days_left' => $daysLeft],
        ];
      }
    }
  }
}
$stmt->close();

usort($notifications, function($a, $b) {
  return strtotime($b['timestamp']) <=> strtotime($a['timestamp']);
});

json_response([
  'ok' => true,
  'notifications' => $notifications,
]);

