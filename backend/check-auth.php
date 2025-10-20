<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$user = require_login($conn);
json_response(['success' => true, 'user' => $user]);
