<?php
// /LibraTrack/backend/books-list.php
session_start();
header('Content-Type: application/json');

// DB
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'libratrack';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB connection failed']);
  exit;
}

$page     = max(1, (int)($_GET['page'] ?? 1));
$pagesize = min(50, max(1, (int)($_GET['pagesize'] ?? 10)));
$q        = trim((string)($_GET['q'] ?? ''));

$where  = "1=1";
$params = [];
$types  = '';
if ($q !== '') {
  $where .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
  $needle = "%{$q}%";
  $params = [$needle, $needle, $needle];
  $types  = 'sss';
}

// Count
$sqlCount = "SELECT COUNT(*) AS c FROM books WHERE $where";
$stmt = $conn->prepare($sqlCount);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

$pages  = max(1, (int)ceil($total / $pagesize));
$offset = ($page - 1) * $pagesize;

// NOTE: matches your schema: date_published, cover
$sql = "SELECT
          id, title, isbn, author, category, quantity, publisher,
          DATE_FORMAT(date_published, '%Y-%m-%d') AS published_at,
          cover
        FROM books
        WHERE $where
        ORDER BY id DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($types) {
  $types2 = $types.'ii';
  $params2 = array_merge($params, [$pagesize, $offset]);
  $stmt->bind_param($types2, ...$params2);
} else {
  $stmt->bind_param('ii', $pagesize, $offset);
}
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
  // Convert DB "cover" to URL (if it's just a filename)
  if (!empty($row['cover']) && $row['cover'][0] !== '/' && !preg_match('~^https?://~i', $row['cover'])) {
    $row['cover_url'] = 'uploads/covers/' . $row['cover'];
  } else {
    $row['cover_url'] = $row['cover'] ?: null;
  }
  unset($row['cover']);

  $row['quantity']     = (int)($row['quantity'] ?? 0);
  $row['published_at'] = $row['published_at'] ?: '';
  $items[] = $row;
}

echo json_encode([
  'ok'       => true,
  'items'    => $items,
  'total'    => $total,
  'page'     => $page,
  'pagesize' => $pagesize,
  'pages'    => $pages
]);
