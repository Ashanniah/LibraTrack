<?php
// backend/favorite-toggle.php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_response(['ok' => false, 'error' => 'Invalid method'], 405);
}

$userId = $_SESSION['user_id'] ?? null;

// Accept either FormData or JSON body
$bookId = $_POST['book_id'] ?? null;
if ($bookId === null) {
  $raw = file_get_contents('php://input');
  if ($raw) {
    $j = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
      $bookId = $j['book_id'] ?? null;
    }
  }
}

if (!$userId || !$bookId) {
  json_response(['ok' => false, 'error' => 'Missing data'], 400);
}

try {
  // prevent duplicates (optional but nice to have)
  $stmt = $pdo->prepare("SELECT 1 FROM favorites WHERE user_id = ? AND book_id = ?");
  $stmt->execute([$userId, $bookId]);

  if ($stmt->fetch()) {
    // unfavorite
    $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND book_id = ?")
        ->execute([$userId, $bookId]);
    json_response(['ok' => true, 'favorited' => false]);
  } else {
    // favorite
    $pdo->prepare("INSERT INTO favorites (user_id, book_id) VALUES (?, ?)")
        ->execute([$userId, $bookId]);
    json_response(['ok' => true, 'favorited' => true]);
  }
} catch (Throwable $e) {
  json_response(['ok' => false, 'error' => $e->getMessage()], 500);
}
