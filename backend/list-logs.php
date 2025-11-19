<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
// Allow both admin and librarian to view logs (with school filtering for librarians)
$u = require_role($conn, ['admin', 'librarian']);

// Ensure table exists (add school_id column if it doesn't exist)
$conn->query("CREATE TABLE IF NOT EXISTS logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  actor VARCHAR(120) NULL,
  type VARCHAR(50) NULL,
  action VARCHAR(120) NULL,
  details TEXT NULL,
  school_id INT(11) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_school_id (school_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Check if school_id column exists, if not add it
$checkSchoolCol = $conn->query("SHOW COLUMNS FROM logs LIKE 'school_id'");
if (!$checkSchoolCol || $checkSchoolCol->num_rows === 0) {
  @$conn->query("ALTER TABLE logs ADD COLUMN school_id INT(11) NULL AFTER details, ADD INDEX idx_school_id (school_id)");
}
if ($checkSchoolCol) $checkSchoolCol->close();

$type = trim($_GET['type'] ?? '');
$from = trim($_GET['from'] ?? '');
$to   = trim($_GET['to'] ?? '');

$conds = [];
$params = [];
$types = '';

// School filtering: Librarians can only see logs from their school
$currentRole = strtolower($u['role'] ?? '');
if ($currentRole === 'librarian') {
  $librarianSchoolId = (int)($u['school_id'] ?? 0);
  if ($librarianSchoolId > 0) {
    $conds[] = "school_id = ?";
    $params[] = $librarianSchoolId;
    $types .= 'i';
  } else {
    // Librarian without school - return empty
    json_response(['success'=>true, 'logs'=>[]]);
  }
}
// Admin sees all logs (no school filter)

if ($type !== '') { $conds[] = "type = ?"; $params[] = $type; $types .= 's'; }
if ($from !== '') { $conds[] = "DATE(created_at) >= ?"; $params[] = $from; $types .= 's'; }
if ($to   !== '') { $conds[] = "DATE(created_at) <= ?"; $params[] = $to;   $types .= 's'; }
$where = $conds ? ("WHERE " . implode(" AND ", $conds)) : '';

$sql = "SELECT actor, COALESCE(action, type) AS action, DATE_FORMAT(created_at,'%Y-%m-%d %H:%i') AS created_at, details 
        FROM logs $where ORDER BY created_at DESC LIMIT 200";
$stmt = $conn->prepare($sql);
if (!$stmt) json_response(['success'=>false,'error'=>'Prepare failed','detail'=>$conn->error], 500);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) $rows[] = $row;
$stmt->close();

json_response(['success'=>true, 'logs'=>$rows]);


