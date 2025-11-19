<?php
// backend/get-book-borrowed-count.php
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

// Count active loans (borrowed and not returned)
$stmt = $conn->prepare('SELECT COUNT(*) AS count FROM loans WHERE book_id = ? AND status = "borrowed"');
$stmt->bind_param('i', $bookId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

json_response([
  'ok' => true,
  'count' => (int)($row['count'] ?? 0)
]);

