<?php
// backend/list-users.php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function respond(array $payload, int $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

if (empty($_SESSION['uid'])) respond(['success'=>false,'message'=>'Unauthorized'], 401);

// Get current user to check role and school_id
$currentUser = current_user($conn);
if (!$currentUser) respond(['success'=>false,'message'=>'Unauthorized'], 401);

$roleFilter = strtolower(trim((string)($_GET['role'] ?? '')));
$q          = trim((string)($_GET['q'] ?? ''));
$limit      = max(1, min(200, (int)($_GET['limit'] ?? 100)));
$offset     = max(0, (int)($_GET['offset'] ?? 0));
$showDeleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] === '1';

$sql = "
  SELECT
    u.id,
    u.first_name, u.middle_name, u.last_name, u.suffix,
    u.email, u.role, u.status,
    u.student_no, u.course, u.year_level, u.section, u.notes,
    u.school_id, s.name AS school_name,
    u.created_at,
    u.deleted_at
  FROM users u
  LEFT JOIN schools s ON s.id = u.school_id
  WHERE 1=1
";
$params = [];
$types  = '';

// School filtering: Librarians can only see users from their school
$currentRole = strtolower($currentUser['role'] ?? '');
if ($currentRole === 'librarian') {
  $librarianSchoolId = (int)($currentUser['school_id'] ?? 0);
  if ($librarianSchoolId > 0) {
    $sql .= " AND u.school_id = ? ";
    $params[] = $librarianSchoolId;
    $types .= 'i';
  } else {
    // Librarian without school assignment - return empty
    respond(['success'=>true, 'users'=>[], 'total'=>0], 200);
  }
}
// Admin sees all users (no school filter)

if ($roleFilter !== '') {
  $sql   .= " AND LOWER(u.role)=? ";
  $params[] = $roleFilter;
  $types   .= 's';
}
if ($q !== '') {
  $like = "%{$q}%";
  $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.student_no LIKE ?) ";
  array_push($params, $like, $like, $like, $like);
  $types .= 'ssss';
}

// Filter deleted users: by default exclude them, unless show_deleted is true
if ($showDeleted) {
  $sql .= " AND u.deleted_at IS NOT NULL ";
} else {
  $sql .= " AND (u.deleted_at IS NULL OR u.deleted_at > NOW()) ";
}

$sql .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ? ";
$params[] = $limit;  $types .= 'i';
$params[] = $offset; $types .= 'i';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res  = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
$stmt->close();

if ($roleFilter === 'student') respond(['success'=>true,'students'=>$rows], 200);
respond(['success'=>true,'users'=>$rows], 200);
