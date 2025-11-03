<?php
// backend/session.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',  // Match db.php - use root path for all subdirectories
    'domain'   => '',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}
if (!isset($_SESSION['user_id']) && isset($_SESSION['uid'])) {
  $_SESSION['user_id'] = $_SESSION['uid'];
}

/* Optional guard — enable if you want auth required for edits
if (empty($_SESSION['uid'])) {
  http_response_code(401);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
  exit;
}
*/
