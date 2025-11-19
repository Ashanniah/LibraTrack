<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
$u = require_role($conn, ['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_response(['success'=>false,'error'=>'Method not allowed'], 405);
}

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
if ($id <= 0) json_response(['success'=>false,'error'=>'Invalid id'], 422);
if ($name === '') json_response(['success'=>false,'error'=>'Name is required'], 422);

$stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
if (!$stmt) json_response(['success'=>false,'error'=>'Prepare failed','detail'=>$conn->error], 500);
$stmt->bind_param('si', $name, $id);
$ok = $stmt->execute();
if (!$ok) json_response(['success'=>false,'error'=>'Execute failed','detail'=>$stmt->error], 500);
$stmt->close();

json_response(['success'=>true]);



