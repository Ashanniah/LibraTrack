<?php
// backend/delete-book.php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');

// 1. Ensure user is librarian or admin
$user = require_role($conn, ['librarian','admin']);

// 2. Validate incoming data
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id < 1) {
  json_response(['ok'=>false,'error'=>'Invalid book id'], 422);
}

// 3. Check if the book exists
$stmt = $conn->prepare("SELECT id, cover FROM books WHERE id=? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$book) {
  json_response(['ok'=>false,'error'=>'Book not found'], 404);
}

// 4. Delete the book
try {
  $del = $conn->prepare("DELETE FROM books WHERE id=?");
  $del->bind_param('i', $id);
  $del->execute();
  $del->close();
} catch (Throwable $e) {
  json_response(['ok'=>false,'error'=>'Database error: '.$e->getMessage()], 500);
}

// 5. (Optional) Remove cover image file if it’s stored locally
if (!empty($book['cover']) && strpos($book['cover'], 'http') !== 0) {
  $path = dirname(__DIR__) . '/uploads/covers/' . basename($book['cover']);
  if (is_file($path)) @unlink($path);
}

// 6. Done
json_response(['ok'=>true,'message'=>'Book deleted successfully']);
