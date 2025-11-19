<?php
// backend/delete-loan.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Only librarians/admins can delete loans
$user = require_role($conn, ['librarian', 'admin']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_response(['ok'=>false,'error'=>'Method not allowed'], 405);
}

$loanId = (int)($_POST['loan_id'] ?? 0);

if ($loanId < 1) {
  json_response(['ok'=>false,'error'=>'Invalid loan ID'], 400);
}

// Get loan details (including student school_id for filtering)
$stmt = $conn->prepare('SELECT l.*, b.id AS book_id, l.status, u.school_id AS student_school_id 
                        FROM loans l 
                        INNER JOIN books b ON b.id = l.book_id 
                        INNER JOIN users u ON u.id = l.student_id 
                        WHERE l.id = ? LIMIT 1');
$stmt->bind_param('i', $loanId);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$loan) {
  json_response(['ok'=>false,'error'=>'Loan not found'], 404);
}

// School isolation: Librarians can only delete loans from their school
$currentRole = strtolower($user['role'] ?? '');
if ($currentRole === 'librarian') {
  $librarianSchoolId = (int)($user['school_id'] ?? 0);
  $loanSchoolId = 0;
  
  // Check if loans table has school_id column
  $checkSchoolCol = $conn->query("SHOW COLUMNS FROM loans LIKE 'school_id'");
  if ($checkSchoolCol && $checkSchoolCol->num_rows > 0) {
    $loanSchoolId = (int)($loan['school_id'] ?? 0);
  } else {
    // Use student's school_id
    $loanSchoolId = (int)($loan['student_school_id'] ?? 0);
  }
  $checkSchoolCol->close();
  
  if ($librarianSchoolId <= 0) {
    json_response(['ok'=>false,'error'=>'No school assigned to librarian'], 403);
  }
  
  if ($loanSchoolId !== $librarianSchoolId) {
    json_response(['ok'=>false,'error'=>'You can only delete loans for students in your school'], 403);
  }
}
// Admin can delete any loan

// Start transaction
$conn->begin_transaction();

try {
  // If loan is active (borrowed), increase book quantity
  if ($loan['status'] === 'borrowed') {
    $stmt = $conn->prepare('UPDATE books SET quantity = quantity + 1 WHERE id = ?');
    $stmt->bind_param('i', $loan['book_id']);
    $stmt->execute();
    $stmt->close();
  }
  
  // Delete the loan
  $stmt = $conn->prepare('DELETE FROM loans WHERE id = ?');
  $stmt->bind_param('i', $loanId);
  $stmt->execute();
  $stmt->close();
  
  $conn->commit();
  json_response(['ok'=>true,'message'=>'Loan deleted successfully']);
} catch (Exception $e) {
  $conn->rollback();
  json_response(['ok'=>false,'error'=>'Failed to delete loan: '.$e->getMessage()], 500);
}

