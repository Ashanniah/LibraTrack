<?php
// backend/count-pending-requests.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Only librarians/admins can view request counts
$user = require_role($conn, ['librarian', 'admin']);

$stmt = $conn->prepare('SELECT COUNT(*) AS count FROM borrow_requests WHERE status = "pending"');
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

json_response([
  'ok' => true,
  'count' => (int)($result['count'] ?? 0)
]);













