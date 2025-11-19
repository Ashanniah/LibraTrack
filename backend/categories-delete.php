<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
$u = require_role($conn, ['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_response(['success'=>false,'error'=>'Method not allowed'], 405);
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) json_response(['success'=>false,'error'=>'Invalid id'], 422);

// Prevent deleting if books reference it
$check = $conn->prepare("SELECT COUNT(*) AS cnt FROM books WHERE category_id = ?");
$check->bind_param('i', $id);
$check->execute();
$rc = $check->get_result()->fetch_assoc();
$check->close();
if (($rc['cnt'] ?? 0) > 0) {
  json_response(['success'=>false,'error'=>'Cannot delete: category is used by existing books']);
}

$stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
if (!$stmt) json_response(['success'=>false,'error'=>'Prepare failed','detail'=>$conn->error], 500);
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
if (!$ok) json_response(['success'=>false,'error'=>'Execute failed','detail'=>$stmt->error], 500);
$stmt->close();

json_response(['success'=>true]);



