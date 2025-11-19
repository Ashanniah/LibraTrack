<?php
// backend/reject-borrow-request.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Only librarians/admins can reject requests
$user = require_role($conn, ['librarian', 'admin']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_response(['ok'=>false,'error'=>'Method not allowed'], 405);
}

$requestId = (int)($_POST['request_id'] ?? 0);
$rejectionReason = trim((string)($_POST['rejection_reason'] ?? ''));

if ($requestId < 1) {
  json_response(['ok'=>false,'error'=>'Invalid request ID'], 400);
}

if ($rejectionReason === '') {
  json_response(['ok'=>false,'error'=>'Rejection reason is required'], 400);
}

// Get request (including student school_id for filtering)
$stmt = $conn->prepare('SELECT br.status, u.school_id AS student_school_id 
                        FROM borrow_requests br 
                        INNER JOIN users u ON u.id = br.student_id 
                        WHERE br.id = ? LIMIT 1');
$stmt->bind_param('i', $requestId);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
  json_response(['ok'=>false,'error'=>'Request not found'], 404);
}

// School isolation: Librarians can only reject requests from their school
$currentRole = strtolower($user['role'] ?? '');
if ($currentRole === 'librarian') {
  $librarianSchoolId = (int)($user['school_id'] ?? 0);
  $studentSchoolId = (int)($request['student_school_id'] ?? 0);
  
  if ($librarianSchoolId <= 0) {
    json_response(['ok'=>false,'error'=>'No school assigned to librarian'], 403);
  }
  
  if ($studentSchoolId !== $librarianSchoolId) {
    json_response(['ok'=>false,'error'=>'You can only reject requests from students in your school'], 403);
  }
}
// Admin can reject any request

if ($request['status'] !== 'pending') {
  json_response(['ok'=>false,'error'=>'Request is not pending'], 400);
}

// Update request status
$stmt = $conn->prepare('UPDATE borrow_requests SET status = "rejected", rejected_at = NOW(), rejection_reason = ?, rejected_by = ? WHERE id = ?');
$stmt->bind_param('sii', $rejectionReason, $user['id'], $requestId);

if ($stmt->execute()) {
  $stmt->close();
  json_response(['ok'=>true,'message'=>'Request rejected successfully']);
} else {
  $stmt->close();
  json_response(['ok'=>false,'error'=>'Failed to reject request: '.$conn->error], 500);
}


