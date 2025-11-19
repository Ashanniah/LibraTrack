<?php
// backend/list-loans.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Only librarians/admins can view loans
$user = require_role($conn, ['librarian', 'admin']);

$status = trim((string)($_GET['status'] ?? ''));
$overdueOnly = isset($_GET['overdue_only']) && $_GET['overdue_only'] === '1';
$activeOnly = isset($_GET['active_only']) && $_GET['active_only'] === '1'; // For Active Loans page
$page = max(1, (int)($_GET['page'] ?? 1));
$pagesize = max(1, min(100, (int)($_GET['pagesize'] ?? 20)));

$where = [];
$types = '';
$args = [];

// School filtering: Librarians can only see loans from their school
$currentRole = strtolower($user['role'] ?? '');
$useSchoolFilter = false;
$useStudentSchoolFilter = false;
if ($currentRole === 'librarian') {
  $librarianSchoolId = (int)($user['school_id'] ?? 0);
  if ($librarianSchoolId > 0) {
    // Check if loans table has school_id column, otherwise filter via student
    try {
      $checkSchoolCol = $conn->query("SHOW COLUMNS FROM loans LIKE 'school_id'");
      if ($checkSchoolCol && $checkSchoolCol->num_rows > 0) {
        // Use school_id directly from loans table
        $where[] = "l.school_id = ?";
        $types .= 'i';
        $args[] = $librarianSchoolId;
        $useSchoolFilter = true;
      } else {
        // Filter via student's school_id (will need JOIN in COUNT query)
        $where[] = "u.school_id = ?";
        $types .= 'i';
        $args[] = $librarianSchoolId;
        $useStudentSchoolFilter = true;
      }
      if ($checkSchoolCol) $checkSchoolCol->close();
    } catch (Exception $e) {
      // If check fails, default to filtering via student
      $where[] = "u.school_id = ?";
      $types .= 'i';
      $args[] = $librarianSchoolId;
      $useStudentSchoolFilter = true;
    }
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
// Admin sees all loans (no school filter)

// Active only: exclude returned loans (for Active Loans page)
if ($activeOnly) {
  $where[] = "l.status = 'borrowed' AND l.returned_at IS NULL";
}

if ($overdueOnly) {
  $where[] = "l.due_at < CURDATE() AND l.status = 'borrowed' AND l.returned_at IS NULL";
}

if ($status !== '' && !$activeOnly) {
  // Only apply status filter if not using active_only
  $where[] = "l.status = ?";
  $types .= 's';
  $args[] = $status;
}

$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Get total count - need JOINs if filtering by student's school_id
if ($useStudentSchoolFilter) {
  $sqlTotal = "SELECT COUNT(*) FROM loans l
               INNER JOIN users u ON u.id = l.student_id
               $whereSQL";
} else {
  $sqlTotal = "SELECT COUNT(*) FROM loans l $whereSQL";
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

// Get loans with book and student info
$sql = "SELECT 
          l.id AS loan_id,
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
          b.category,
          b.cover,
          u.id AS student_id,
          u.first_name,
          u.last_name,
          u.student_no,
          u.course,
          u.year_level,
          u.section
        FROM loans l
        INNER JOIN books b ON b.id = l.book_id
        INNER JOIN users u ON u.id = l.student_id
        $whereSQL
        ORDER BY l.borrowed_at DESC
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
$today = date('Y-m-d');
while ($row = $res->fetch_assoc()) {
  $row['cover_url'] = $row['cover'] ? ("/uploads/covers/".$row['cover']) : null;
  $row['student_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
  
  // Calculate status - check both status field and returned_at
  if ($row['status'] === 'returned' || !empty($row['returned_at'])) {
    $row['loan_status'] = 'returned';
  } elseif ($row['status'] === 'borrowed' && empty($row['returned_at'])) {
    // Only calculate overdue/on-time for active (non-returned) loans
    $dueDate = $row['extended_due_at'] ?? $row['due_at'];
    if ($dueDate && $dueDate < $today) {
      $row['loan_status'] = 'overdue';
    } else {
      $row['loan_status'] = 'on-time';
    }
  } else {
    // Default for other statuses
    $row['loan_status'] = 'borrowed';
  }
  
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


