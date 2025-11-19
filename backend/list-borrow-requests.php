<?php
// backend/list-borrow-requests.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Only librarians/admins can view borrow requests
$user = require_role($conn, ['librarian', 'admin']);

$status = trim((string)($_GET['status'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$pagesize = max(1, min(100, (int)($_GET['pagesize'] ?? 20)));

$where = [];
$types = '';
$args = [];

// School filtering: Librarians can only see requests from their school
$currentRole = strtolower($user['role'] ?? '');
$useStudentSchoolFilter = false;
if ($currentRole === 'librarian') {
  $librarianSchoolId = (int)($user['school_id'] ?? 0);
  if ($librarianSchoolId > 0) {
    $where[] = "u.school_id = ?";
    $types .= 'i';
    $args[] = $librarianSchoolId;
    $useStudentSchoolFilter = true;
  } else {
    // Librarian without school - return empty
    json_response([
      'ok' => true,
      'items' => [],
      'total' => 0,
      'page' => $page,
      'pages' => 0,
      'pagesize' => $pagesize
    ]);
  }
}
// Admin sees all requests (no school filter)

if ($status !== '') {
  $where[] = "br.status = ?";
  $types .= 's';
  $args[] = $status;
}

$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Get total count - borrow_requests always needs JOINs when school filtering is used
// (since we filter via student's school_id which requires users table)
if ($useStudentSchoolFilter) {
  $sqlTotal = "SELECT COUNT(*) FROM borrow_requests br
               INNER JOIN users u ON u.id = br.student_id
               $whereSQL";
} else {
  $sqlTotal = "SELECT COUNT(*) FROM borrow_requests br $whereSQL";
}

$stmt = $conn->prepare($sqlTotal);
if (!$stmt) {
  json_response(['ok' => false, 'error' => 'Failed to prepare query: ' . $conn->error], 500);
}
if ($types) $stmt->bind_param($types, ...$args);
if (!$stmt->execute()) {
  $stmt->close();
  json_response(['ok' => false, 'error' => 'Query execution failed: ' . $stmt->error], 500);
}
$stmt->store_result();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();

$pages = max(1, (int)ceil($total / $pagesize));
$offset = ($page - 1) * $pagesize;

// Get requests with book and student info
$sql = "SELECT 
          br.id AS request_id,
          br.duration_days,
          br.status,
          br.requested_at,
          br.approved_at,
          br.rejected_at,
          br.rejection_reason,
          br.librarian_note,
          b.id AS book_id,
          b.title AS book_title,
          b.isbn,
          b.author,
          b.category,
          b.quantity AS book_quantity,
          b.cover,
          u.id AS student_id,
          u.first_name,
          u.last_name,
          u.student_no,
          u.course,
          u.year_level,
          u.section
        FROM borrow_requests br
        INNER JOIN books b ON b.id = br.book_id
        INNER JOIN users u ON u.id = br.student_id
        $whereSQL
        ORDER BY br.requested_at DESC
        LIMIT ? OFFSET ?";

$types2 = $types . 'ii';
$args2 = $args;
$args2[] = $pagesize;
$args2[] = $offset;

$stmt = $conn->prepare($sql);
if (!$stmt) {
  json_response(['ok' => false, 'error' => 'Failed to prepare query: ' . $conn->error], 500);
}
$stmt->bind_param($types2, ...$args2);
if (!$stmt->execute()) {
  $stmt->close();
  json_response(['ok' => false, 'error' => 'Query execution failed: ' . $stmt->error], 500);
}
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
  $row['cover_url'] = $row['cover'] ? ("/uploads/covers/".$row['cover']) : null;
  $row['student_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
  $items[] = $row;
}
$stmt->close();

json_response([
  'ok' => true,
  'items' => $items,
  'total' => $total,
  'page' => $page,
  'pages' => $pages,
  'pagesize' => $pagesize
]);


