<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start(); // just in case

// Clear session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
}
session_destroy();

$loginUrl = '/libratrack/login.html';

// If the request looks like AJAX/JSON -> return JSON
$accept  = $_SERVER['HTTP_ACCEPT'] ?? '';
$isAjax  = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
if (str_contains($accept, 'application/json') || $isAjax) {
  header('Content-Type: application/json');
  echo json_encode(['success' => true, 'redirect' => $loginUrl]);
  exit;
}

// Otherwise, perform a real redirect (works for <a href="backend/logout.php">)
header('Location: ' . $loginUrl, true, 302);
exit;
