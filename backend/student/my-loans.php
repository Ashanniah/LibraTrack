<?php
// backend/student/my-loans.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$student = require_login($conn);
$role = strtolower((string)($student['role'] ?? ''));
if (!in_array($role, ['student','admin','librarian'], true)) {
  json_response(['ok'=>false,'error'=>'Forbidden'], 403);
}

$studentId = $role === 'student' ? (int)$student['id'] : (int)($_GET['student_id'] ?? 0);
if ($role !== 'student' && $studentId < 1) {
  json_response(['ok'=>false,'error'=>'student_id is required'], 400);
}
if ($role === 'student') {
  $studentId = (int)$student['id'];
}

$status = strtolower(trim((string)($_GET['status'] ?? '')));
$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = max(1, min(100, (int)($_GET['pagesize'] ?? 50)));
$offset = ($page - 1) * $pageSize;

$where = ['l.student_id = ?'];
$types = 'i';
$params = [$studentId];

if ($status === 'returned') {
  $where[] = "l.status = 'returned'";
} elseif ($status === 'borrowed') {
  $where[] = "l.status = 'borrowed'";
  $where[] = "l.due_at >= CURDATE()";
} elseif ($status === 'overdue') {
  $where[] = "l.status = 'borrowed'";
  $where[] = "l.due_at < CURDATE()";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Total count
$stmt = $conn->prepare("SELECT COUNT(*) FROM loans l $whereSql");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

$pages = max(1, (int)ceil($total / $pageSize));

$sql = "SELECT
          l.id,
          l.request_id,
          l.borrowed_at,
          l.due_at,
          l.returned_at,
          l.status,
          l.extended_due_at,
          b.id AS book_id,
          b.title AS book_title,
          b.isbn,
          b.author,
          b.cover,
          b.category
        FROM loans l
        INNER JOIN books b ON b.id = l.book_id
        $whereSql
        ORDER BY l.borrowed_at DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$types2 = $types . 'ii';
$params2 = $params;
$params2[] = $pageSize;
$params2[] = $offset;
$stmt->bind_param($types2, ...$params2);
$stmt->execute();
$res = $stmt->get_result();

$today = new DateTimeImmutable('today');
$items = [];
while ($row = $res->fetch_assoc()) {
  $due = $row['due_at'] ? new DateTimeImmutable($row['due_at']) : null;
  $returned = $row['returned_at'] ? new DateTimeImmutable($row['returned_at']) : null;

  if ($row['status'] === 'returned') {
    $row['loan_status'] = 'returned';
  } elseif ($due && $due < $today) {
    $row['loan_status'] = 'overdue';
  } else {
    $row['loan_status'] = 'borrowed';
  }

  $row['cover_url'] = $row['cover'] ? ("/uploads/covers/".$row['cover']) : null;
  $row['days_late'] = ($row['loan_status'] === 'overdue' && $due)
    ? $due->diff($today)->days
    : 0;
  $row['due_in_days'] = ($row['loan_status'] === 'borrowed' && $due)
    ? $today->diff($due)->days
    : 0;

  $items[] = $row;
}
$stmt->close();

json_response([
  'ok' => true,
  'items' => $items,
  'total' => $total,
  'page' => $page,
  'pages' => $pages
]);

