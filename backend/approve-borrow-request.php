<?php
// backend/approve-borrow-request.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Check method first
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_response(['ok'=>false,'error'=>'Method not allowed'], 405);
}

// Only librarians/admins can approve requests
$user = require_role($conn, ['librarian', 'admin']);

$requestId = (int)($_POST['request_id'] ?? 0);
$durationDays = (int)($_POST['duration_days'] ?? 0);
$librarianNote = trim((string)($_POST['librarian_note'] ?? ''));

if ($requestId < 1) {
  json_response(['ok'=>false,'error'=>'Invalid request ID'], 400);
}

if (!in_array($durationDays, [3, 7, 14], true)) {
  json_response(['ok'=>false,'error'=>'Invalid duration. Must be 3, 7, or 14 days'], 400);
}

// Get request details (including student school_id for filtering)
$stmt = $conn->prepare('SELECT br.*, b.quantity AS book_quantity, u.school_id AS student_school_id 
                        FROM borrow_requests br 
                        INNER JOIN books b ON b.id = br.book_id 
                        INNER JOIN users u ON u.id = br.student_id 
                        WHERE br.id = ? LIMIT 1');
$stmt->bind_param('i', $requestId);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
  json_response(['ok'=>false,'error'=>'Request not found'], 404);
}

// School isolation: Librarians can only approve requests from their school
$currentRole = strtolower($user['role'] ?? '');
if ($currentRole === 'librarian') {
  $librarianSchoolId = (int)($user['school_id'] ?? 0);
  $studentSchoolId = (int)($request['student_school_id'] ?? 0);
  
  if ($librarianSchoolId <= 0) {
    json_response(['ok'=>false,'error'=>'No school assigned to librarian'], 403);
  }
  
  if ($studentSchoolId !== $librarianSchoolId) {
    json_response(['ok'=>false,'error'=>'You can only approve requests from students in your school'], 403);
  }
}
// Admin can approve any request

if ($request['status'] !== 'pending') {
  json_response(['ok'=>false,'error'=>'Request is not pending'], 400);
}

if (($request['book_quantity'] ?? 0) < 1) {
  json_response(['ok'=>false,'error'=>'Book is not available'], 400);
}

// Start transaction
$conn->begin_transaction();

try {
  // Update request status
  $stmt = $conn->prepare('UPDATE borrow_requests SET status = "approved", approved_at = NOW(), duration_days = ?, librarian_note = ?, approved_by = ? WHERE id = ?');
  if (!$stmt) {
    throw new Exception('Prepare failed: ' . $conn->error);
  }
  
  // Ensure correct types
  $durationDays = (int)$durationDays;
  $userId = (int)$user['id'];
  $requestId = (int)$requestId;
  
  $stmt->bind_param('isii', $durationDays, $librarianNote, $userId, $requestId);
  if (!$stmt->execute()) {
    throw new Exception('Execute failed: ' . $stmt->error);
  }
  $stmt->close();

  // Calculate due date
  $dueDate = date('Y-m-d', strtotime("+{$durationDays} days"));

  // Create loan record (with school_id if column exists)
  $studentSchoolId = (int)($request['student_school_id'] ?? 0);
  
  // Check if loans table has school_id column
  $checkSchoolCol = $conn->query("SHOW COLUMNS FROM loans LIKE 'school_id'");
  $hasSchoolId = $checkSchoolCol && $checkSchoolCol->num_rows > 0;
  $checkSchoolCol->close();
  
  // Ensure correct types
  $studentId = (int)$request['student_id'];
  $bookId = (int)$request['book_id'];
  
  if ($hasSchoolId) {
    $stmt = $conn->prepare('INSERT INTO loans (request_id, student_id, book_id, school_id, borrowed_at, due_at, status, created_at) VALUES (?, ?, ?, ?, NOW(), ?, "borrowed", NOW())');
    if (!$stmt) {
      throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('iiiis', $requestId, $studentId, $bookId, $studentSchoolId, $dueDate);
  } else {
    $stmt = $conn->prepare('INSERT INTO loans (request_id, student_id, book_id, borrowed_at, due_at, status, created_at) VALUES (?, ?, ?, NOW(), ?, "borrowed", NOW())');
    if (!$stmt) {
      throw new Exception('Prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('iiis', $requestId, $studentId, $bookId, $dueDate);
  }
  
  if (!$stmt->execute()) {
    throw new Exception('Execute failed: ' . $stmt->error);
  }
  $loanId = $conn->insert_id;
  $stmt->close();

  // Decrease book quantity
  $stmt = $conn->prepare('UPDATE books SET quantity = quantity - 1 WHERE id = ?');
  if (!$stmt) {
    throw new Exception('Prepare failed: ' . $conn->error);
  }
  
  $bookId = (int)$request['book_id'];
  $stmt->bind_param('i', $bookId);
  if (!$stmt->execute()) {
    throw new Exception('Execute failed: ' . $stmt->error);
  }
  $stmt->close();

  $conn->commit();
  json_response(['ok'=>true,'loan_id'=>$loanId,'message'=>'Request approved and book issued successfully']);
} catch (Exception $e) {
  $conn->rollback();
  error_log('Approve request error: ' . $e->getMessage());
  error_log('Stack trace: ' . $e->getTraceAsString());
  json_response(['ok'=>false,'error'=>'Failed to approve request: '.$e->getMessage()], 500);
}


