<?php
// backend/favorites-list.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';

$uid  = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : (int)($_SESSION['user']['id'] ?? 0);
$role = strtolower($_SESSION['role'] ?? ($_SESSION['user']['role'] ?? ''));

if ($uid <= 0) json_response(['ok'=>false,'error'=>'Unauthorized'], 401);
if ($role !== 'student') json_response(['ok'=>false,'error'=>'Only students have favorites'], 403);

$sql = "SELECT b.id, b.title, b.author, b.category, b.publisher, b.isbn,
               b.quantity, DATE_FORMAT(b.date_published,'%Y-%m-%d') AS published_at,
               b.cover_url
        FROM favorites f
        JOIN books b ON b.id = f.book_id
        WHERE f.user_id = ?
        ORDER BY f.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $uid);
$stmt->execute();
$res = $stmt->get_result();
$items = [];
while ($row = $res->fetch_assoc()) $items[] = $row;
$stmt->close();

json_response(['ok'=>true, 'items'=>$items]);
