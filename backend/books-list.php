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

$sql = "SELECT id,title,isbn,author,category,quantity,publisher,date_published,cover,created_at,
               COALESCE(rating, 0) AS rating
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
while ($row = $res->fetch_assoc()) {
  $row['cover_url']    = $row['cover'] ? ("/libratrack/uploads/covers/".$row['cover']) : null;
  $row['published_at'] = $row['date_published'];
  $items[]             = $row;
}
$stmt->close();

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
