<?php
// backend/books-list.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php'; // session + $conn + $pdo

$page     = max(1, (int)($_GET['page'] ?? 1));
$pagesize = max(1, min(50, (int)($_GET['pagesize'] ?? 12)));
$q        = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));

$where = [];
$types = '';
$args  = [];

if ($q !== '') {
  $where[] = "(title LIKE CONCAT('%', ?, '%') OR author LIKE CONCAT('%', ?, '%') OR isbn LIKE CONCAT('%', ?, '%'))";
  $types .= 'sss'; $args[] = $q; $args[] = $q; $args[] = $q;
}
if ($category !== '') {
  $where[] = "category = ?";
  $types .= 's'; $args[] = $category;
}

$whereSQL = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// total
$sqlTotal = "SELECT COUNT(*) FROM books $whereSQL";
$stmt = $conn->prepare($sqlTotal);
if ($types) $stmt->bind_param($types, ...$args);
$stmt->execute(); $stmt->store_result(); $stmt->bind_result($total); $stmt->fetch(); $stmt->close();

$pages  = max(1, (int)ceil($total / $pagesize));
$offset = ($page - 1) * $pagesize;

// Check if archived column exists
$checkArchived = $conn->query("SHOW COLUMNS FROM books LIKE 'archived'");
$hasArchived = $checkArchived->num_rows > 0;
$checkArchived->close();

$archivedField = $hasArchived ? ', COALESCE(archived, 0) AS archived' : ', 0 AS archived';

$sql = "SELECT id,title,isbn,author,category,quantity,publisher,date_published,cover,created_at,
               COALESCE(rating, 0) AS rating
               $archivedField
        FROM books
        $whereSQL
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?";
$types2 = $types.'ii';
$args2  = $args; $args2[] = $pagesize; $args2[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types2, ...$args2);
$stmt->execute(); $res = $stmt->get_result();

$items = [];
$bookIds = [];
while ($row = $res->fetch_assoc()) {
  $row['cover_url']    = $row['cover'] ? ("/uploads/covers/".$row['cover']) : null;
  $row['published_at'] = $row['date_published'];
  $row['archived']     = (int)($row['archived'] ?? 0);
  $items[]             = $row;
  $bookIds[]           = (int)$row['id'];
}
$stmt->close();

// Calculate borrowed counts for each book (filtered by school for librarians)
$borrowedCounts = [];
if (!empty($bookIds)) {
  // Check if user is logged in and get their role/school
  $currentUser = null;
  if (!empty($_SESSION['uid'])) {
    require_once __DIR__ . '/auth.php';
    $currentUser = current_user($conn);
  }
  
  $in = implode(',', array_fill(0, count($bookIds), '?'));
  $schoolFilter = '';
  $borrowedParams = $bookIds;
  $borrowedTypes = str_repeat('i', count($bookIds));
  
  // School filtering: Librarians see borrowed counts only for their school
  if ($currentUser && strtolower($currentUser['role'] ?? '') === 'librarian') {
    $librarianSchoolId = (int)($currentUser['school_id'] ?? 0);
    if ($librarianSchoolId > 0) {
      // Check if loans table has school_id column
      $checkSchoolCol = $conn->query("SHOW COLUMNS FROM loans LIKE 'school_id'");
      if ($checkSchoolCol && $checkSchoolCol->num_rows > 0) {
        // Use school_id directly from loans table
        $schoolFilter = " AND l.school_id = ?";
        $borrowedParams[] = $librarianSchoolId;
        $borrowedTypes .= 'i';
      } else {
        // Filter via student's school_id
        $schoolFilter = " AND EXISTS (SELECT 1 FROM users u WHERE u.id = l.student_id AND u.school_id = ?)";
        $borrowedParams[] = $librarianSchoolId;
        $borrowedTypes .= 'i';
      }
      $checkSchoolCol->close();
    }
  }
  // Admin sees all borrowed counts (no school filter)
  
  $sqlBorrowed = "SELECT l.book_id, COUNT(*) AS count 
                  FROM loans l 
                  WHERE l.book_id IN ($in) AND l.status = 'borrowed' 
                  $schoolFilter
                  GROUP BY l.book_id";
  $stmt = $conn->prepare($sqlBorrowed);
  $stmt->bind_param($borrowedTypes, ...$borrowedParams);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($r = $result->fetch_assoc()) {
    $borrowedCounts[(int)$r['book_id']] = (int)$r['count'];
  }
  $stmt->close();
  
  // Attach borrowed counts to items
  foreach ($items as &$it) {
    $it['borrowed'] = $borrowedCounts[(int)$it['id']] ?? 0;
  }
  unset($it);
}

// attach is_favorite for logged-in students
$user = $_SESSION['user'] ?? null;
if ($user && strtolower((string)($user['role'] ?? '')) === 'student' && $items) {
  $ids = array_column($items, 'id');
  $in  = implode(',', array_fill(0, count($ids), '?'));
  $typesFav = str_repeat('i', count($ids)+1);
  $argsFav  = array_merge([(int)$user['id']], $ids);

  $stmt = $conn->prepare("SELECT book_id FROM favorites WHERE user_id = ? AND book_id IN ($in)");
  $stmt->bind_param($typesFav, ...$argsFav);
  $stmt->execute();
  $res = $stmt->get_result();
  $favSet = [];
  while ($r = $res->fetch_assoc()) $favSet[(int)$r['book_id']] = true;
  $stmt->close();

  foreach ($items as &$it) $it['is_favorite'] = isset($favSet[(int)$it['id']]);
} else {
  foreach ($items as &$it) $it['is_favorite'] = false;
}

echo json_encode([
  'ok'       => true,
  'page'     => $page,
  'pages'    => $pages,
  'pagesize' => $pagesize,
  'total'    => (int)$total,
  'items'    => $items,
], JSON_UNESCAPED_UNICODE);
