<?php
// backend/me.php
declare(strict_types=1);
header('Content-Type: application/json');

session_start();
if (empty($_SESSION['user'])) {
  http_response_code(401);
  echo json_encode(['success'=>false, 'message'=>'Not authenticated']);
  exit;
}

echo json_encode([
  'success' => true,
  'user'    => $_SESSION['user']
]);