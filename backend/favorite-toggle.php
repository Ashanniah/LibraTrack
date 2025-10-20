<?php
// backend/favorite-toggle.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php'; // starts session, gives $conn, has helpers

// Allow only POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_response(['ok'=>false,'error'=>'Method not allowed'], 405);
}

/**
 * Resolve current user from session.
 * Supports either:
 *   - $_SESSION['uid'], $_SESSION['role']   (your db.php helpers)
 *   - $_SESSION['user'] = ['id'=>...,'role'=>...] (some pages use this)
 */
$uid  = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : 0;
$role = isset($_SESSION['role']) ? (string)$_SESSION['role'] : '';

if (!$uid && isset($_SESSION['user']) && is_array($_SESSION['user'])) {
  $uid  = (int)($_SESSION['user']['id']   ?? 0);
  $role = (string)($_SESSION['user']['role'] ?? '');
}

if ($uid <= 0) {
  json_response(['ok'=>false,'error'=>'Unauthorized'], 401);
}

if (strtolower($role) !== 'student') {
  json_response(['ok'=>false,'error'=>'Only students can favorite'], 403);
}

// Accept JSON or form
$raw  = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true);
$bookId = 0;

if (is_array($body) && isset($body['book_id'])) {
  $bookId = (int)$body['book_id'];
} elseif (isset($_POST['book_id'])) {
  $bookId = (int)$_POST['book_id'];
}

if ($bookId <= 0) {
  json_response(['ok'=>false,'error'=>'Invalid book_id'], 400);
}

// Ensure book exists
$stmt = $conn->prepare('SELECT id FROM books WHERE id=? LIMIT 1');
$stmt->bind_param('i', $bookId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
  $stmt->close();
  json_response(['ok'=>false,'error'=>'Book not found'], 404);
}
$stmt->close();

// Toggle favorite
$stmt = $conn->prepare('SELECT 1 FROM favorites WHERE user_id=? AND book_id=?');
$stmt->bind_param('ii', $uid, $bookId);
$stmt->execute();
$stmt->store_result();
$exists = $stmt->num_rows > 0;
$stmt->close();

if ($exists) {
  $stmt = $conn->prepare('DELETE FROM favorites WHERE user_id=? AND book_id=?');
  $stmt->bind_param('ii', $uid, $bookId);
  $stmt->execute();
  $stmt->close();
  json_response(['ok'=>true,'favorited'=>false]);
} else {
  $stmt = $conn->prepare('INSERT INTO favorites (user_id, book_id, created_at) VALUES (?,?,NOW())');
  $stmt->bind_param('ii', $uid, $bookId);
  $stmt->execute();
  $stmt->close();
  json_response(['ok'=>true,'favorited'=>true]);
}
