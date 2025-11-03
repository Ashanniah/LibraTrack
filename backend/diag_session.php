<?php
// backend/_diag_session.php
require_once __DIR__ . '/db.php';
$_SESSION['ping'] = ($_SESSION['ping'] ?? 0) + 1;

echo "<pre>";
echo "SID:        ", session_id(), PHP_EOL;
echo "ping:       ", $_SESSION['ping'], PHP_EOL;
echo "save_path:  ", ini_get('session.save_path'), PHP_EOL;
echo "cookie:     ", (isset($_COOKIE[session_name()]) ? "present" : "missing"), PHP_EOL;
print_r(session_get_cookie_params());
echo "</pre>";
