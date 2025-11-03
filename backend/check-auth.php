<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

try {
  if (isset($_GET['debug'])) {
    json_response([
      'debug' => [
        'session_id'       => session_id(),
        'has_uid'          => isset($_SESSION['uid']),
        'uid'              => $_SESSION['uid'] ?? null,
        'cookie_params'    => session_get_cookie_params(),
        'cookies_received' => $_COOKIE,
      ]
    ]);
  }

  $user = require_login($conn);
  json_response(['success' => true, 'user' => $user]);

} catch (Throwable $e) {
  // Never throw HTML—always return JSON so the dashboard JS can handle it.
  json_response([
    'success' => false,
    'error'   => 'check-auth fatal',
    'detail'  => $e->getMessage(),
  ], 500);
}
