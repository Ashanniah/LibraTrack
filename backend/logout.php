<?php
declare(strict_types=1);
session_start();

// Log out
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

$redirectUrl = '/libratrack/login.html';

// If the client asked for JSON (AJAX/fetch), return JSON; else redirect.
$acceptsJson = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'],'application/json') !== false)
               || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_GET['json']) && $_GET['json'] === '1');

if ($acceptsJson) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success' => true, 'redirect' => $redirectUrl]);
  exit;
}

header('Location: ' . $redirectUrl, true, 302);
exit;
