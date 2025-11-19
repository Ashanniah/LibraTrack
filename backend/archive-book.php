<?php
// backend/archive-book.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Only librarians/admins can archive
$user = require_role($conn, ['librarian', 'admin']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$id = (int)($_POST['id'] ?? 0);
$archived = (int)($_POST['archived'] ?? 0); // 1 = archived, 0 = not archived

if ($id <= 0) {
  json_response(['ok' => false, 'error' => 'Invalid book ID'], 400);
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

// Check if archived column exists, if not, use a workaround
// First, try to update archived column
try {
  // Check if column exists
  $checkStmt = $conn->query("SHOW COLUMNS FROM books LIKE 'archived'");
  $columnExists = $checkStmt->num_rows > 0;
  $checkStmt->close();
  
  if ($columnExists) {
    // Column exists, use it
    $stmt = $conn->prepare('UPDATE books SET archived = ? WHERE id = ?');
    $stmt->bind_param('ii', $archived, $id);
  } else {
    // Column doesn't exist, we'll add it
    // For now, return an error suggesting to add the column
    // Or we can add it dynamically here
    try {
      $conn->query("ALTER TABLE books ADD COLUMN archived TINYINT(1) DEFAULT 0 AFTER quantity");
      $stmt = $conn->prepare('UPDATE books SET archived = ? WHERE id = ?');
      $stmt->bind_param('ii', $archived, $id);
    } catch (Exception $e) {
      // Column might already exist or there's an issue
      json_response(['ok' => false, 'error' => 'Could not update archive status. Please ensure the "archived" column exists in the books table.'], 500);
    }
  }
  
  if (!$stmt->execute()) {
    throw new Exception($stmt->error);
  }
  $stmt->close();
  
  json_response(['ok' => true, 'message' => 'Book ' . ($archived ? 'archived' : 'unarchived') . ' successfully']);
} catch (Throwable $e) {
  json_response(['ok' => false, 'error' => 'Operation failed: ' . $e->getMessage()], 500);
}

