<?php
require __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

$page     = max(1, (int)($_GET['page'] ?? 1));
$pagesize = min(50, max(1, (int)($_GET['pagesize'] ?? 10)));
$q        = trim($_GET['q'] ?? '');

$where = '';
$params = [];
$types  = '';

if ($q !== '') {
  $where = "WHERE (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
  $like = '%'.$q.'%';
  $params = [$like,$like,$like];
  $types  = 'sss';
}

try {
  // total
  $sqlTotal = "SELECT COUNT(*) AS c FROM books $where";
  $stmt = $conn->prepare($sqlTotal);
  if ($types) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $total = (int)$stmt->get_result()->fetch_assoc()['c'];

  // page data
  $offset = ($page - 1) * $pagesize;
  $sql = "SELECT id,title,isbn,author,category,quantity,publisher,
                 DATE_FORMAT(date_published,'%Y-%m-%d') AS date_published,
                 cover, created_at
          FROM books
          $where
          ORDER BY created_at DESC
          LIMIT ? OFFSET ?";
  $stmt2 = $conn->prepare($sql);

  if ($types) {
    $types2 = $types . 'ii';
    $stmt2->bind_param($types2, ...$params, $pagesize, $offset);
  } else {
    $stmt2->bind_param('ii', $pagesize, $offset);
  }
  $stmt2->execute();
  $rows = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

  echo json_encode([
    'ok'       => true,
    'items'    => $rows,
    'total'    => $total,
    'page'     => $page,
    'pagesize' => $pagesize,
    'pages'    => max(1, (int)ceil($total / $pagesize))
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
