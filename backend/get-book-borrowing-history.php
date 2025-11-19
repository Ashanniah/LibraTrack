<?php
// backend/get-book-borrowing-history.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Only librarians/admins can view
$user = require_role($conn, ['librarian', 'admin']);

$bookId = (int)($_GET['book_id'] ?? 0);
if ($bookId <= 0) {
  json_response(['ok' => false, 'error' => 'Invalid book ID'], 400);
}

// Get borrowing history with student information
$sql = "SELECT 
          l.id AS loan_id,
          l.borrowed_at,
          l.due_at,
          l.returned_at,
          l.status,
          u.id AS student_id,
          u.first_name,
          u.last_name,
          u.student_no,
          u.course,
          u.year_level,
          u.section
        FROM loans l
        INNER JOIN users u ON u.id = l.student_id
        WHERE l.book_id = ?
        ORDER BY l.borrowed_at DESC
        LIMIT 100";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $bookId);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
  // Calculate status (borrowed, returned, overdue)
  $status = $row['status'];
  if ($status === 'borrowed' && $row['due_at']) {
    $dueDate = strtotime($row['due_at']);
    $today = strtotime('today');
    if ($dueDate < $today) {
      $status = 'overdue';
    }
  }
  
  $history[] = [
    'loan_id' => (int)$row['loan_id'],
    'student_id' => (int)$row['student_id'],
    'first_name' => $row['first_name'] ?? '',
    'last_name' => $row['last_name'] ?? '',
    'student_no' => $row['student_no'] ?? '',
    'course' => $row['course'] ?? '',
    'year_level' => $row['year_level'] ?? '',
    'section' => $row['section'] ?? '',
    'borrowed_at' => $row['borrowed_at'] ?? null,
    'due_at' => $row['due_at'] ?? null,
    'returned_at' => $row['returned_at'] ?? null,
    'status' => $status
  ];
}

$stmt->close();

json_response([
  'ok' => true,
  'history' => $history
]);

