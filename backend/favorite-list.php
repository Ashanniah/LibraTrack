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
    SELECT b.id, b.title, b.author, b.cover_url, b.category, b.isbn, b.quantity, b.publisher, b.date_published AS published_at
    FROM favorites f
    JOIN books b ON b.id = f.book_id
    WHERE f.user_id = ?
    ORDER BY f.id DESC
  ");
  $stmt->execute([$userId]);
  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok' => true, 'items' => $items]);
} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
