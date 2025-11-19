<?php
// backend/create-borrow-request.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Only students can create borrow requests
$user = require_role($conn, ['student']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_response(['ok'=>false,'error'=>'Method not allowed'], 405);
}

$bookId = (int)($_POST['book_id'] ?? 0);
$durationDays = (int)($_POST['duration_days'] ?? 7);

if ($bookId < 1) {
  json_response(['ok'=>false,'error'=>'Invalid book ID'], 400);
}

if (!in_array($durationDays, [3, 7, 14], true)) {
  json_response(['ok'=>false,'error'=>'Invalid duration. Must be 3, 7, or 14 days'], 400);
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

if (($book['quantity'] ?? 0) < 1) {
  json_response(['ok'=>false,'error'=>'Book is not available'], 400);
}

// Check if student already has a pending request for this book
$stmt = $conn->prepare('SELECT id FROM borrow_requests WHERE student_id = ? AND book_id = ? AND status = "pending" LIMIT 1');
$stmt->bind_param('ii', $user['id'], $bookId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
  $stmt->close();
  json_response(['ok'=>false,'error'=>'You already have a pending request for this book'], 409);
}
$stmt->close();

// Create borrow request
$stmt = $conn->prepare('INSERT INTO borrow_requests (student_id, book_id, duration_days, status, requested_at) VALUES (?, ?, ?, "pending", NOW())');
$stmt->bind_param('iii', $user['id'], $bookId, $durationDays);

if ($stmt->execute()) {
  $requestId = $conn->insert_id;
  $stmt->close();
  json_response(['ok'=>true,'request_id'=>$requestId,'message'=>'Borrow request submitted successfully']);
} else {
  $stmt->close();
  json_response(['ok'=>false,'error'=>'Failed to create borrow request: '.$conn->error], 500);
}


