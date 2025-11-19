<?php
// backend/favorites-list.php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

header('Content-Type: application/json');

// accept both uid or user_id
$userId = $_SESSION['user_id'] ?? ($_SESSION['uid'] ?? null);
if (!$userId) {
  echo json_encode(['ok' => false, 'error' => 'Not logged in']);
  exit;
}

try {
  $stmt = $pdo->prepare("
    SELECT b.id, b.title, b.author, b.cover, 
           b.category, b.isbn, b.quantity, b.publisher, 
           DATE_FORMAT(b.date_published, '%Y-%m-%d') AS published_at
    FROM favorites f
    JOIN books b ON b.id = f.book_id
    WHERE f.user_id = ?
    ORDER BY f.id DESC
  ");
  $stmt->execute([$userId]);
  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Add cover_url with proper path
  foreach ($items as &$item) {
    $item['cover_url'] = $item['cover'] ? "/uploads/covers/" . $item['cover'] : null;
  }
  unset($item); // Important: unset reference after loop
  
  echo json_encode(['ok' => true, 'items' => $items]);
} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
