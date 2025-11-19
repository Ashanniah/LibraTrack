<?php
// backend/create-loan.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Only librarians/admins can create loans directly
$user = require_role($conn, ['librarian', 'admin']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_response(['ok'=>false,'error'=>'Method not allowed'], 405);
}

$studentId = (int)($_POST['student_id'] ?? 0);
$bookId = (int)($_POST['book_id'] ?? 0);
$borrowedAt = trim((string)($_POST['borrowed_at'] ?? ''));
$dueAt = trim((string)($_POST['due_at'] ?? ''));

if ($studentId < 1) {
  json_response(['ok'=>false,'error'=>'Invalid student ID'], 400);
}

if ($bookId < 1) {
  json_response(['ok'=>false,'error'=>'Invalid book ID'], 400);
}

if (!$borrowedAt) {
  $borrowedAt = date('Y-m-d H:i:s');
}

if (!$dueAt) {
  json_response(['ok'=>false,'error'=>'Due date is required'], 400);
}

// Validate dates
try {
  $borrowedDate = new DateTime($borrowedAt);
  $dueDate = new DateTime($dueAt);
  if ($dueDate <= $borrowedDate) {
    json_response(['ok'=>false,'error'=>'Due date must be after borrowed date'], 400);
  }
} catch (Exception $e) {
  json_response(['ok'=>false,'error'=>'Invalid date format'], 400);
}

// Check if student exists and get their school_id
$stmt = $conn->prepare('SELECT id, school_id FROM users WHERE id = ? AND role = "student" LIMIT 1');
$stmt->bind_param('i', $studentId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
  json_response(['ok'=>false,'error'=>'Student not found'], 404);
}

// School isolation: Librarians can only create loans for students in their school
$currentRole = strtolower($user['role'] ?? '');
if ($currentRole === 'librarian') {
  $librarianSchoolId = (int)($user['school_id'] ?? 0);
  $studentSchoolId = (int)($student['school_id'] ?? 0);
  
  if ($librarianSchoolId <= 0) {
    json_response(['ok'=>false,'error'=>'No school assigned to librarian'], 403);
  }
  
  if ($studentSchoolId !== $librarianSchoolId) {
    json_response(['ok'=>false,'error'=>'You can only create loans for students in your school'], 403);
  }
}

// Check if book exists and has available quantity
$stmt = $conn->prepare('SELECT id, title, quantity FROM books WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $bookId);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$book) {
  json_response(['ok'=>false,'error'=>'Book not found'], 404);
}

// Check available quantity (considering active loans)
$stmt = $conn->prepare('SELECT COUNT(*) as borrowed_count FROM loans WHERE book_id = ? AND status = "borrowed"');
$stmt->bind_param('i', $bookId);
$stmt->execute();
$borrowedResult = $stmt->get_result()->fetch_assoc();
$stmt->close();

$borrowedCount = (int)($borrowedResult['borrowed_count'] ?? 0);
$available = (int)($book['quantity'] ?? 0) - $borrowedCount;

if ($available < 1) {
  json_response(['ok'=>false,'error'=>'Book is not available (no copies left)'], 400);
}

// Start transaction
$conn->begin_transaction();

try {
  // Check if loans table has school_id column
  $checkSchoolCol = $conn->query("SHOW COLUMNS FROM loans LIKE 'school_id'");
  $hasSchoolId = $checkSchoolCol && $checkSchoolCol->num_rows > 0;
  $checkSchoolCol->close();
  
  $studentSchoolId = (int)($student['school_id'] ?? 0);
  
  // Create loan record (with school_id if column exists)
  if ($hasSchoolId) {
    $stmt = $conn->prepare('INSERT INTO loans (student_id, book_id, school_id, borrowed_at, due_at, status, created_at) VALUES (?, ?, ?, ?, ?, "borrowed", NOW())');
    if (!$stmt) {
      throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('iiiss', $studentId, $bookId, $studentSchoolId, $borrowedAt, $dueAt);
  } else {
    $stmt = $conn->prepare('INSERT INTO loans (student_id, book_id, borrowed_at, due_at, status, created_at) VALUES (?, ?, ?, ?, "borrowed", NOW())');
    if (!$stmt) {
      throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('iiss', $studentId, $bookId, $borrowedAt, $dueAt);
  }
  
  if (!$stmt->execute()) {
    throw new Exception('Execute failed: ' . $stmt->error);
  }
  
  $loanId = $conn->insert_id;
  $stmt->close();
  
  $conn->commit();
  json_response([
    'ok' => true,
    'message' => 'Loan created successfully',
    'loan_id' => $loanId
  ]);
} catch (Exception $e) {
  $conn->rollback();
  json_response(['ok'=>false,'error'=>'Failed to create loan: '.$e->getMessage()], 500);
}

