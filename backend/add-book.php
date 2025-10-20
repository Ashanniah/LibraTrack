<?php
// /LibraTrack/backend/add-book.php
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

/* If you want to enforce login later:
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Not authenticated']); exit; }
*/

$title          = trim($_POST['title'] ?? '');
$isbn           = trim($_POST['isbn'] ?? '');
$author         = trim($_POST['author'] ?? '');
$category       = trim($_POST['category'] ?? '');
$quantity       = (int)($_POST['quantity'] ?? 0);
$publisher      = trim($_POST['publisher'] ?? '');
$date_published = $_POST['date_published'] ?? null;

if ($title === '' || $isbn === '' || $author === '' || $category === '' || $quantity < 1 || $publisher === '') {
  http_response_code(422);
  echo json_encode(['ok'=>false,'error'=>'Missing required fields']);
  exit;
}
if ($date_published === '') $date_published = null;

// Upload cover (save filename in `cover`)
$coverFilename = null;
if (!empty($_FILES['cover']['name'])) {
  $ext = strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION));
  $allowed = ['png','jpg','jpeg','webp','gif'];
  if (!in_array($ext, $allowed, true)) {
    http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Invalid image type']); exit;
  }
  if ($_FILES['cover']['size'] > 5*1024*1024) {
    http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Image too large (max 5MB)']); exit;
  }

  $uploadsFs = dirname(__DIR__) . '/uploads/covers/';
  if (!is_dir($uploadsFs)) @mkdir($uploadsFs, 0777, true);

  $coverFilename = uniqid('cover_', true) . '.' . $ext;
  if (!move_uploaded_file($_FILES['cover']['tmp_name'], $uploadsFs . $coverFilename)) {
    http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Failed to save image']); exit;
  }
}

// Only columns that exist in your table
$sql = "INSERT INTO books
          (title, isbn, author, category, quantity, publisher, date_published, cover, created_at)
        VALUES
          (?,?,?,?,?,?,?,?,NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param(
  'ssssisss',
  $title, $isbn, $author, $category, $quantity, $publisher, $date_published, $coverFilename
);

try {
  $stmt->execute();
  $cover_url = $coverFilename ? ('uploads/covers/' . $coverFilename) : null;
  echo json_encode(['ok'=>true, 'id'=>$stmt->insert_id, 'cover_url'=>$cover_url]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'DB error']);
}
