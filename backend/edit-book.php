<?php
// backend/edit-book.php
declare(strict_types=1);

// Make sure errors don't leak to browser
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json');

require_once __DIR__ . '/session.php';   // << no underscore
require_once __DIR__ . '/db.php';        // provides $conn (mysqli)

function fail(string $msg, int $code = 400): never {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_SLASHES);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  fail('Method not allowed', 405);
}

// -------- collect & validate --------
$id             = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$title          = trim((string)($_POST['title'] ?? ''));
$isbn           = trim((string)($_POST['isbn'] ?? ''));
$author         = trim((string)($_POST['author'] ?? ''));
$category       = trim((string)($_POST['category'] ?? ''));
$quantity       = (int)($_POST['quantity'] ?? 0);
$publisher      = trim((string)($_POST['publisher'] ?? ''));
$date_published = $_POST['date_published'] ?? null;

if ($id < 1 || $title === '' || $isbn === '' || $author === '' || $category === '' || $quantity < 1 || $publisher === '') {
  fail('Missing required fields', 422);
}

// Normalize date: allow empty -> NULL; accept dd/mm/yyyy or yyyy-mm-dd
if ($date_published === '' || $date_published === null) {
  $date_published = null;
} else {
  $raw = (string)$date_published;
  $dt  = \DateTime::createFromFormat('d/m/Y', $raw) ?: \DateTime::createFromFormat('Y-m-d', $raw);
  if (!$dt) {
    // last fallback
    $ts = strtotime($raw);
    if ($ts === false) fail('Invalid date_published', 422);
    $dt = (new \DateTime())->setTimestamp($ts);
  }
  $date_published = $dt->format('Y-m-d');
}

// -------- update --------
try {
  $sql = "UPDATE books
             SET title = ?, author = ?, category = ?, publisher = ?, isbn = ?, quantity = ?, date_published = ?
           WHERE id = ?";

  $stmt = $conn->prepare($sql);
  // s s s s s i s i
  $stmt->bind_param('sssssisi', $title, $author, $category, $publisher, $isbn, $quantity, $date_published, $id);
  $stmt->execute();

  echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  error_log('edit-book failed: ' . $e->getMessage());
  fail('DB error', 500);
}
