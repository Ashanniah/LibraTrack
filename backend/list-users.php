<?php
// backend/list-users.php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php'; // must define $pdo (PDO), optionally json_response()

/**
 * We’ll use PDO for consistency (your db.php already exposes $pdo in other files).
 * json_response() is used if available; otherwise we fall back to echo+http_response_code.
 */
function respond($payload, int $code = 200) {
  if (function_exists('json_response')) {
    json_response($payload, $code);
  } else {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  }
  exit;
}

try {
  if (!isset($_SESSION['uid'])) {
    respond(['success'=>false,'message'=>'Unauthorized'], 401);
  }

  $sessionRole = strtolower($_SESSION['role'] ?? '');
  $uid         = (int)($_SESSION['uid'] ?? 0);
  if ($uid <= 0) {
    respond(['success'=>false,'message'=>'Unauthorized'], 401);
  }

  // Optional filters
  $roleFilter = strtolower(trim((string)($_GET['role'] ?? '')));   // e.g. 'student'
  $schoolIdQ  = isset($_GET['school_id']) ? (int)$_GET['school_id'] : null;

  // Resolve librarian school (for scoping when role=student)
  $schoolId   = (int)($_SESSION['user']['school_id'] ?? 0);
  $schoolName = (string)($_SESSION['user']['school_name'] ?? '');

  if ($sessionRole === 'librarian') {
    if ($schoolId <= 0) {
      $stmt = $pdo->prepare("
        SELECT u.school_id, s.name AS school_name
        FROM users u
        LEFT JOIN schools s ON s.id = u.school_id
        WHERE u.id = ?
        LIMIT 1
      ");
      $stmt->execute([$uid]);
      if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $schoolId   = (int)($row['school_id'] ?? 0);
        $schoolName = (string)($row['school_name'] ?? '');
        $_SESSION['user']['school_id']   = $schoolId;
        $_SESSION['user']['school_name'] = $schoolName;
      }
    }
    if ($roleFilter === 'student' && $schoolId <= 0) {
      respond(['success'=>false,'message'=>'No school assigned to this librarian.'], 409);
    }
  }

  // Build SQL
  $sql = "
    SELECT
      u.id,
      u.first_name,
      u.middle_name,
      u.last_name,
      u.suffix,
      u.email,
      u.role,
      u.status,
      u.created_at,
      u.school_id,
      u.student_no,
      u.course,
      u.year_level,
      u.section,
      s.name AS school_name
    FROM users u
    LEFT JOIN schools s ON s.id = u.school_id
    WHERE 1=1
  ";
  $params = [];

  if ($roleFilter !== '') {
    $sql .= " AND LOWER(u.role) = ? ";
    $params[] = $roleFilter;
  }

  // Librarian scoping: if asking for students, force to their school
  if ($sessionRole === 'librarian' && $roleFilter === 'student') {
    $sql .= " AND u.school_id = ? ";
    $params[] = $schoolId;
  }

  // Admin may explicitly filter by school_id (optional)
  if ($sessionRole === 'admin' && $schoolIdQ !== null && $schoolIdQ > 0) {
    $sql .= " AND u.school_id = ? ";
    $params[] = $schoolIdQ;
  }

  $sql .= " ORDER BY u.id DESC ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Back-compat with pages expecting "students" when role=student
  if ($roleFilter === 'student') {
    respond([
      'success'     => true,
      'school_id'   => ($sessionRole === 'librarian') ? $schoolId : ($schoolIdQ ?? null),
      'school_name' => ($sessionRole === 'librarian') ? $schoolName : null,
      'students'    => $rows
    ], 200);
  }

  // Default shape for generic user lists
  respond([
    'success' => true,
    'users'   => $rows
  ], 200);

} catch (Throwable $e) {
  respond(['success'=>false,'message'=>'Server error','error'=>$e->getMessage()], 500);
}
