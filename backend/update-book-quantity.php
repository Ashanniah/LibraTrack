<?php
// backend/update-book-quantity.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Only librarians/admins can update
$user = require_role($conn, ['librarian', 'admin']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$id = (int)($_POST['id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 0);

if ($id <= 0) {
  json_response(['ok' => false, 'error' => 'Invalid book ID'], 400);
}

if ($quantity < 0) {
  json_response(['ok' => false, 'error' => 'Quantity cannot be negative'], 400);
}

// Check if book exists
$stmt = $conn->prepare('SELECT id FROM books WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
  $stmt->close();
  json_response(['ok' => false, 'error' => 'Book not found'], 404);
}
$stmt->close();

// Update quantity
try {
  $stmt = $conn->prepare('UPDATE books SET quantity = ? WHERE id = ?');
  $stmt->bind_param('ii', $quantity, $id);
  if (!$stmt->execute()) {
    throw new Exception($stmt->error);
  }
  $stmt->close();
  
  json_response(['ok' => true, 'message' => 'Quantity updated successfully']);
} catch (Throwable $e) {
  json_response(['ok' => false, 'error' => 'Update failed: ' . $e->getMessage()], 500);
}

