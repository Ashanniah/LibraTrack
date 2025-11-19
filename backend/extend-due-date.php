<?php
// backend/extend-due-date.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Only librarians/admins can extend due dates
$user = require_role($conn, ['librarian', 'admin']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_response(['ok'=>false,'error'=>'Method not allowed'], 405);
}

$loanId = (int)($_POST['loan_id'] ?? 0);
$newDueDate = trim((string)($_POST['new_due_date'] ?? ''));

if ($loanId < 1) {
  json_response(['ok'=>false,'error'=>'Invalid loan ID'], 400);
}

if ($newDueDate === '') {
  json_response(['ok'=>false,'error'=>'New due date is required'], 400);
}

// Validate date format
$date = date_create_from_format('Y-m-d', $newDueDate);
if (!$date) {
  json_response(['ok'=>false,'error'=>'Invalid date format. Use YYYY-MM-DD'], 400);
}

// Get loan (including student school_id for filtering)
$stmt = $conn->prepare('SELECT l.status, u.school_id AS student_school_id 
                        FROM loans l 
                        INNER JOIN users u ON u.id = l.student_id 
                        WHERE l.id = ? LIMIT 1');
$stmt->bind_param('i', $loanId);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$loan) {
  json_response(['ok'=>false,'error'=>'Loan not found'], 404);
}

// School isolation: Librarians can only extend due dates for loans from their school
$currentRole = strtolower($user['role'] ?? '');
if ($currentRole === 'librarian') {
  $librarianSchoolId = (int)($user['school_id'] ?? 0);
  $loanSchoolId = (int)($loan['student_school_id'] ?? 0);
  
  if ($librarianSchoolId <= 0) {
    json_response(['ok'=>false,'error'=>'No school assigned to librarian'], 403);
  }
  
  if ($loanSchoolId !== $librarianSchoolId) {
    json_response(['ok'=>false,'error'=>'You can only extend due dates for loans from your school'], 403);
  }
}
// Admin can extend any loan

if ($loan['status'] !== 'borrowed') {
  json_response(['ok'=>false,'error'=>'Loan is not active'], 400);
}

// Update due date
$stmt = $conn->prepare('UPDATE loans SET extended_due_at = ?, due_at = ? WHERE id = ?');
$stmt->bind_param('ssi', $newDueDate, $newDueDate, $loanId);

if ($stmt->execute()) {
  $stmt->close();
  json_response(['ok'=>true,'message'=>'Due date extended successfully']);
} else {
  $stmt->close();
  json_response(['ok'=>false,'error'=>'Failed to extend due date: '.$conn->error], 500);
}



